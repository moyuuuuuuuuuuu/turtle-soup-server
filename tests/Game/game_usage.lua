-- Run the production Lua scripts against deterministic in-memory Redis command doubles.
-- This checks script behavior; it does not replace a real Redis concurrency integration test.
local now = 1700000000
local strings, sets, expirations = {}, {}, {}
local quotas = {
    {window = 60, limit = 4}, {window = 86400, limit = 6},
    {window = 60, limit = 8}, {window = 86400, limit = 10},
    {window = 86400, limit = 12}
}
local locks = {1, 1, 2, 3}
cjson = {decode = function(value) return value == 'quotas' and quotas or locks end}
redis = {call = function(command, key, a, b)
    if command == 'TIME' then return {tostring(now), '0'} end
    if expirations[key] and expirations[key] <= now then
        strings[key], sets[key], expirations[key] = nil, nil, nil
    end
    if command == 'GET' then return strings[key] end
    if command == 'INCRBY' then strings[key] = (strings[key] or 0) + tonumber(a); return strings[key] end
    if command == 'EXPIRE' then expirations[key] = now + tonumber(a); return 1 end
    if command == 'ZREMRANGEBYSCORE' then
        for member, score in pairs(sets[key] or {}) do
            if score <= tonumber(b) then sets[key][member] = nil end
        end
        return 1
    end
    if command == 'ZCARD' then
        local count = 0
        for _ in pairs(sets[key] or {}) do count = count + 1 end
        return count
    end
    if command == 'ZADD' then sets[key] = sets[key] or {}; sets[key][b] = tonumber(a); return 1 end
    if command == 'ZREM' then if sets[key] then sets[key][a] = nil end; return 1 end
    error('Unsupported Redis command: ' .. command)
end}

local function keys(identity, game, ip)
    return {identity .. ':minute', identity .. ':day', ip .. ':minute', ip .. ':day', 'global:day',
        'active:' .. identity, 'game:' .. game, 'active:' .. ip, 'active:global'}
end
local function admit(identity, game, ip, lease)
    KEYS = keys(identity, game, ip)
    ARGV = {'quotas', 'locks', '2', lease, '300'}
    return acquire()
end
local function finish(identity, game, ip, lease)
    local all = keys(identity, game, ip)
    KEYS = {all[6], all[7], all[8], all[9]}
    ARGV = {lease}
    return release()
end
local function reset()
    strings, sets, expirations = {}, {}, {}
end
local function count(key, window)
    return strings[key .. ':' .. math.floor(now / window)] or 0
end

-- A second in-flight request is rejected and does not consume further budget.
assert(admit('user1', 'game1', 'ip1', 'lease1') == 1)
assert(admit('user1', 'game2', 'ip1', 'lease2') == 2)
assert(count('global:day', 86400) == 2)
-- Room members share the game lock even with different identities.
assert(admit('user2', 'game1', 'ip2', 'lease2') == 2)
finish('user1', 'game1', 'ip1', 'lease1')
assert(admit('user1', 'game2', 'ip1', 'lease2') == 1)
finish('user1', 'game2', 'ip1', 'lease2')
assert(admit('user1', 'game3', 'ip1', 'lease3') == 0)
assert(count('global:day', 86400) == 4)

-- Minute reset does not reset the daily budget.
now = now + 60
assert(admit('user1', 'game3', 'ip1', 'lease3') == 1)
finish('user1', 'game3', 'ip1', 'lease3')
now = now + 60
assert(admit('user1', 'game4', 'ip1', 'lease4') == 0)
assert(count('user1:day', 86400) == 6)

-- New anonymous identities cannot escape the IP budget.
reset()
for i = 1, 4 do
    assert(admit('guest' .. i, 'game' .. i, 'ip1', 'lease' .. i) == 1)
    finish('guest' .. i, 'game' .. i, 'ip1', 'lease' .. i)
end
assert(admit('guest5', 'game5', 'ip1', 'lease5') == 0)
assert(count('guest5:day', 86400) == 0)
assert(count('global:day', 86400) == 8)

-- Distinct users/IPs still share the global budget.
reset()
for i = 1, 6 do
    assert(admit('user' .. i, 'game' .. i, 'ip' .. i, 'lease' .. i) == 1)
    finish('user' .. i, 'game' .. i, 'ip' .. i, 'lease' .. i)
end
assert(admit('user7', 'game7', 'ip7', 'lease7') == 0)
assert(count('user7:day', 86400) == 0)

-- Crashed-worker leases expire, and an old worker cannot release the replacement lease.
reset()
assert(admit('user1', 'game1', 'ip1', 'old') == 1)
now = now + 301
assert(admit('user1', 'game1', 'ip1', 'new') == 1)
finish('user1', 'game1', 'ip1', 'old')
assert(admit('user1', 'game1', 'ip1', 'third') == 2)
finish('user1', 'game1', 'ip1', 'new')
assert(admit('user1', 'game1', 'ip1', 'third') == 1)

-- IP and global concurrent ceilings apply across identities and games.
reset()
assert(admit('user1', 'game1', 'ip1', 'one') == 1)
assert(admit('user2', 'game2', 'ip1', 'two') == 1)
assert(admit('user3', 'game3', 'ip1', 'three') == 2)
assert(admit('user3', 'game3', 'ip2', 'three') == 1)
assert(admit('user4', 'game4', 'ip3', 'four') == 2)
assert(count('global:day', 86400) == 6)
print('Game usage Lua checks passed: quotas, shared limits, admission, expiry, release ownership')
