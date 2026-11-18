const tabs = document.querySelectorAll('.tab');
const architectures = document.querySelectorAll('.architecture');

tabs.forEach((tab) => {
  tab.addEventListener('click', () => {
    tabs.forEach((item) => {
      const active = item === tab;
      item.classList.toggle('active', active);
      item.setAttribute('aria-pressed', String(active));
    });
    architectures.forEach((panel) => {
      panel.hidden = panel.id !== tab.dataset.target;
    });
  });
});

const revealElements = document.querySelectorAll('[data-reveal]');
if ('IntersectionObserver' in window) {
  const revealObserver = new IntersectionObserver((entries, observer) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12 });
  revealElements.forEach((element) => revealObserver.observe(element));
} else {
  revealElements.forEach((element) => element.classList.add('visible'));
}

const progressBar = document.getElementByById('progressBar');
const chapterLinks = document.querySelectorAll('.chapter-nav a');
const chapters = [...chapterLinks].map((link) => document.querySelector(link.getAttribute('href'))).filter(Boolean);

const updateScrollUI = () => {
  const scrollable = document.documentElement.scrollHeight - window.innerHeight;
  const progress = scrollable > 0 ? window.scrollY / scrollable : 0;
  progressBar.style.width = `${Math.min(progress * 100, 100)}%`;

  let activeChapter = chapters[0];
  chapters.forEach((chapter) => {
    if (chapter.getBoundingClientRect().top <= window.innerHeight * 0.35) activeChapter = chapter;
  });
  chapterLinks.forEach((link) => {
    link.classList.toggle('active', link.getAttribute('href') === `#${activeChapter.id}`);
  });
};

window.addEventListener('scroll', updateScrollUI, { passive: true });
updateScrollUI();

document.querySelectorAll('[data-tilt]').forEach((card) => {
  card.addEventListener('pointermove', (event) => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    const rect = card.getBoundingClientRect();
    const x = (event.clientX - rect.left) / rect.width - 0.5;
    const y = (event.clientY - rect.top) / rect.height - 0.5;
    card.style.transform = `rotateY(${x * 5}deg) rotateX(${y * -5}deg) translateY(-3px)`;
  });
  card.addEventListener('pointerleave', () => {
    card.style.transform = '';
  });
});

