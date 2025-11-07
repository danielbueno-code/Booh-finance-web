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

// ===== MENÚ HAMBURGUESA ROBUSTO =====
document.addEventListener("DOMContentLoaded", () => {
  const toggle = document.getElementById("menu-toggle");
  const nav = document.getElementById("nav-links");

  if (!toggle || !nav) return;

  const closeMenu = () => {
    toggle.classList.remove("active");
    nav.classList.remove("show");
    toggle.setAttribute("aria-expanded", "false");
    document.body.style.overflow = ""; // re-habilita scroll
  };

  const openMenu = () => {
    toggle.classList.add("active");
    nav.classList.add("show");
    toggle.setAttribute("aria-expanded", "true");
    document.body.style.overflow = "hidden"; // bloquea scroll de fondo
  };

  toggle.addEventListener("click", () => {
    const isOpen = nav.classList.contains("show");
    isOpen ? closeMenu() : openMenu();
  });

  // Cierra al pulsar un enlace
  nav.querySelectorAll("a").forEach(a =>
    a.addEventListener("click", closeMenu)
  );
});
