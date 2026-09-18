(() => {
  const links = Array.from(document.querySelectorAll('.toc a[href^="#"]'))
  const sections = links
    .map((link) => document.querySelector(link.getAttribute('href')))
    .filter(Boolean)

  if (!links.length || !sections.length) {
    return
  }

  const setActive = (id) => {
    links.forEach((link) => {
      link.classList.toggle('active', link.getAttribute('href') === `#${id}`)
    })
  }

  const observer = new IntersectionObserver(
    (entries) => {
      const visible = entries
        .filter((entry) => entry.isIntersecting)
        .sort((a, b) => b.intersectionRatio - a.intersectionRatio)
      if (visible[0]?.target?.id) {
        setActive(visible[0].target.id)
      }
    },
    {
      rootMargin: '-20% 0px -60% 0px',
      threshold: [0.1, 0.35, 0.6]
    }
  )

  sections.forEach((section) => observer.observe(section))
  setActive(sections[0].id)
})()
