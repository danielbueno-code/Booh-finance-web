document.addEventListener("DOMContentLoaded", () => {
  const fadeEls = document.querySelectorAll(".fade-in");

  // Fallback: si el navegador no soporta IntersectionObserver
  if (!("IntersectionObserver" in window)) {
    fadeEls.forEach(el => el.classList.add("appear"));
    return;
  }

  const observer = new IntersectionObserver((entries, obs) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add("appear");
        obs.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1, rootMargin: "0px 0px -10% 0px" });

  fadeEls.forEach(el => observer.observe(el));
});
