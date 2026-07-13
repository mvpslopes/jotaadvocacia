/* =========================================================================
   JOTA Advocacia — interações do site
   ========================================================================= */
(function () {
  "use strict";

  var WHATSAPP_NUMBER = "5531982445112";
  var reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  /* -----------------------------------------------------------------
     0. Splash screen + estado inicial da página
     ----------------------------------------------------------------- */
  var splash = document.getElementById("splash");

  function markPageLoaded() {
    document.body.classList.remove("is-splashing");
    document.body.classList.add("is-loaded");
  }

  if (reducedMotion || !splash) {
    if (splash) splash.classList.add("is-hidden");
    markPageLoaded();
  } else if (window.localStorage && window.localStorage.getItem("jota_splash_seen") === "1") {
    splash.classList.add("is-hidden");
    markPageLoaded();
  } else {
    document.body.classList.add("is-splashing");
    window.addEventListener("load", function () {
      setTimeout(hideSplash, 1800);
    });
    setTimeout(hideSplash, 2800);
  }

  function hideSplash() {
    if (!splash) {
      markPageLoaded();
      return;
    }
    if (window.localStorage) {
      window.localStorage.setItem("jota_splash_seen", "1");
    }
    splash.classList.add("is-exiting");
    setTimeout(function () {
      splash.classList.add("is-hidden");
      splash.setAttribute("aria-hidden", "true");
      markPageLoaded();
    }, 650);
  }

  /* -----------------------------------------------------------------
     1. Cabeçalho: estado "rolado" + menu mobile
     ----------------------------------------------------------------- */
  var header = document.querySelector(".site-header");
  var navToggle = document.getElementById("navToggle");
  var mainNav = document.getElementById("mainNav");
  var navOverlay = document.getElementById("navOverlay");

  function updateHeaderState() {
    if (!header) return;
    if (window.scrollY > 40) {
      header.classList.add("is-scrolled");
    } else {
      header.classList.remove("is-scrolled");
    }
  }
  updateHeaderState();
  window.addEventListener("scroll", updateHeaderState, { passive: true });

  function closeMobileNav() {
    mainNav.classList.remove("is-open");
    navOverlay.classList.remove("is-open");
    navToggle.setAttribute("aria-expanded", "false");
    document.body.style.overflow = "";
  }

  function toggleMobileNav() {
    var isOpen = mainNav.classList.toggle("is-open");
    navOverlay.classList.toggle("is-open", isOpen);
    navToggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
    document.body.style.overflow = isOpen ? "hidden" : "";
  }

  if (navToggle && mainNav) {
    navToggle.addEventListener("click", toggleMobileNav);
    navOverlay.addEventListener("click", closeMobileNav);
    mainNav.querySelectorAll("a").forEach(function (link) {
      link.addEventListener("click", closeMobileNav);
    });
  }

  /* -----------------------------------------------------------------
     2. Animações de entrada ao rolar a página
     ----------------------------------------------------------------- */
  var animatedEls = document.querySelectorAll("[data-animate]");

  if ("IntersectionObserver" in window) {
    var observer = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-visible");
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.15, rootMargin: "0px 0px -40px 0px" }
    );
    animatedEls.forEach(function (el) {
      observer.observe(el);
    });
  } else {
    animatedEls.forEach(function (el) {
      el.classList.add("is-visible");
    });
  }

  /* -----------------------------------------------------------------
     2b. Animação em cascata nos grids (data-stagger)
     ----------------------------------------------------------------- */
  var staggerContainers = document.querySelectorAll("[data-stagger]");

  staggerContainers.forEach(function (container) {
    var children = container.children;
    for (var i = 0; i < children.length; i++) {
      children[i].style.setProperty("--i", i);
    }
  });

  if ("IntersectionObserver" in window && !reducedMotion) {
    var staggerObserver = new IntersectionObserver(
      function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          var kids = entry.target.children;
          for (var j = 0; j < kids.length; j++) {
            kids[j].classList.add("is-visible");
          }
          staggerObserver.unobserve(entry.target);
        });
      },
      { threshold: 0.12, rootMargin: "0px 0px -30px 0px" }
    );
    staggerContainers.forEach(function (container) {
      staggerObserver.observe(container);
    });
  } else {
    staggerContainers.forEach(function (container) {
      Array.prototype.forEach.call(container.children, function (child) {
        child.classList.add("is-visible");
      });
    });
  }

  /* -----------------------------------------------------------------
     2c. Barra de progresso de rolagem
     ----------------------------------------------------------------- */
  var scrollProgress = document.getElementById("scrollProgress");

  function updateScrollProgress() {
    if (!scrollProgress) return;
    var scrollTop = window.scrollY || document.documentElement.scrollTop;
    var docHeight = document.documentElement.scrollHeight - window.innerHeight;
    var percent = docHeight > 0 ? (scrollTop / docHeight) * 100 : 0;
    scrollProgress.style.width = percent + "%";
  }
  updateScrollProgress();
  window.addEventListener("scroll", updateScrollProgress, { passive: true });

  /* -----------------------------------------------------------------
     2d. Slideshow do hero + indicadores
     ----------------------------------------------------------------- */
  var heroSlides = document.querySelectorAll(".hero-slide");
  var heroDots = document.querySelectorAll(".hero-dot");
  var heroIndex = 0;
  var heroTimer;

  function setHeroSlide(index) {
    if (!heroSlides.length) return;
    heroIndex = index;
    heroSlides.forEach(function (slide, i) {
      slide.classList.toggle("is-active", i === index);
    });
    heroDots.forEach(function (dot, i) {
      var isActive = i === index;
      dot.classList.toggle("is-active", isActive);
      dot.setAttribute("aria-current", isActive ? "true" : "false");
    });
  }

  function nextHeroSlide() {
    setHeroSlide((heroIndex + 1) % heroSlides.length);
  }

  function startHeroSlideshow() {
    if (heroSlides.length < 2 || reducedMotion) return;
    window.clearInterval(heroTimer);
    heroTimer = window.setInterval(nextHeroSlide, 6500);
  }

  if (heroSlides.length) {
    heroDots.forEach(function (dot) {
      dot.addEventListener("click", function () {
        var target = Number(dot.getAttribute("data-slide"));
        if (Number.isNaN(target)) return;
        setHeroSlide(target);
        startHeroSlideshow();
      });
    });
    startHeroSlideshow();
  }

  /* -----------------------------------------------------------------
     2e. Menu ativo conforme a seção visível
     ----------------------------------------------------------------- */
  var navLinks = document.querySelectorAll(".main-nav a[href^='#']");
  var sectionIds = ["sobre", "servicos", "como-funciona", "depoimentos", "faq", "contato"];
  var navSections = sectionIds
    .map(function (id) {
      return document.getElementById(id);
    })
    .filter(Boolean);

  function updateActiveNav() {
    if (!navLinks.length || !navSections.length) return;
    var scrollPos = window.scrollY + 140;
    var currentId = "";

    navSections.forEach(function (section) {
      if (scrollPos >= section.offsetTop) {
        currentId = section.id;
      }
    });

    navLinks.forEach(function (link) {
      var href = link.getAttribute("href");
      link.classList.toggle("is-active", href === "#" + currentId);
    });
  }

  updateActiveNav();
  window.addEventListener("scroll", updateActiveNav, { passive: true });

  /* -----------------------------------------------------------------
     3. Acordeão de FAQ
     ----------------------------------------------------------------- */
  var faqItems = document.querySelectorAll(".faq-item");

  function setAnswerHeight(item, open) {
    var answer = item.querySelector(".faq-answer");
    if (!answer) return;
    if (open) {
      answer.style.height = answer.scrollHeight + "px";
    } else {
      answer.style.height = "0px";
    }
  }

  faqItems.forEach(function (item) {
    var question = item.querySelector(".faq-question");
    setAnswerHeight(item, item.classList.contains("is-open"));

    question.addEventListener("click", function () {
      var wasOpen = item.classList.contains("is-open");

      faqItems.forEach(function (other) {
        other.classList.remove("is-open");
        other.querySelector(".faq-question").setAttribute("aria-expanded", "false");
        setAnswerHeight(other, false);
      });

      if (!wasOpen) {
        item.classList.add("is-open");
        question.setAttribute("aria-expanded", "true");
        setAnswerHeight(item, true);
      }
    });
  });

  window.addEventListener("resize", function () {
    faqItems.forEach(function (item) {
      if (item.classList.contains("is-open")) {
        setAnswerHeight(item, true);
      }
    });
  });

  /* -----------------------------------------------------------------
     4. Formulário de contato -> PHP -> redireciona ao WhatsApp
     ----------------------------------------------------------------- */
  var leadForm = document.getElementById("leadForm");
  var feedbackBox = document.getElementById("formFeedback");
  var submitBtn = document.getElementById("leadFormSubmit");

  function showFeedback(message, type) {
    if (!feedbackBox) return;
    feedbackBox.textContent = message;
    feedbackBox.className = "form-feedback is-visible " + type;
  }

  function buildWhatsAppMessage(data) {
    var linhas = [
      "Ola, Dra. Josi! Meu nome e " + data.nome + ".",
      "Assunto: " + data.assunto + ".",
    ];
    if (data.mensagem) {
      linhas.push("Detalhes: " + data.mensagem);
    }
    linhas.push("Vim pelo site da JOTA Advocacia e gostaria de uma analise do meu caso.");
    return linhas.join(" ");
  }

  if (leadForm) {
    leadForm.addEventListener("submit", function (event) {
      event.preventDefault();

      var nome = leadForm.nome.value.trim();
      var telefone = leadForm.telefone.value.trim();
      var assunto = leadForm.assunto.value;

      if (!nome || !telefone || !assunto) {
        showFeedback("Por favor, preencha nome, WhatsApp e assunto antes de enviar.", "error");
        return;
      }

      var formData = new FormData(leadForm);
      submitBtn.disabled = true;
      submitBtn.style.opacity = "0.7";
      showFeedback("Enviando suas informações...", "success");

      fetch(leadForm.action, {
        method: "POST",
        body: formData,
        headers: { "X-Requested-With": "XMLHttpRequest" },
      })
        .then(function (response) {
          return response.json().catch(function () {
            return { success: response.ok };
          });
        })
        .then(function (result) {
          if (result && result.success === false) {
            showFeedback(result.message || "Não foi possível enviar agora. Tente novamente ou fale direto no WhatsApp.", "error");
            submitBtn.disabled = false;
            submitBtn.style.opacity = "1";
            return;
          }

          showFeedback("Recebemos sua mensagem! Redirecionando para o WhatsApp...", "success");

          var mensagem = buildWhatsAppMessage({
            nome: nome,
            assunto: assunto,
            mensagem: leadForm.mensagem.value.trim(),
          });
          var url = "https://wa.me/" + WHATSAPP_NUMBER + "?text=" + encodeURIComponent(mensagem);

          setTimeout(function () {
            window.location.href = url;
            submitBtn.disabled = false;
            submitBtn.style.opacity = "1";
          }, 900);
        })
        .catch(function () {
          // mesmo se o backend falhar (ex.: ambiente sem PHP configurado),
          // garantimos que o objetivo principal da página seja cumprido.
          showFeedback("Redirecionando para o WhatsApp...", "success");
          var mensagem = buildWhatsAppMessage({
            nome: nome,
            assunto: assunto,
            mensagem: leadForm.mensagem.value.trim(),
          });
          var url = "https://wa.me/" + WHATSAPP_NUMBER + "?text=" + encodeURIComponent(mensagem);
          setTimeout(function () {
            window.location.href = url;
            submitBtn.disabled = false;
            submitBtn.style.opacity = "1";
          }, 900);
        });
    });
  }

  /* -----------------------------------------------------------------
     5. Ano atual no rodapé
     ----------------------------------------------------------------- */
  var anoEl = document.getElementById("anoAtual");
  if (anoEl) {
    anoEl.textContent = new Date().getFullYear();
  }
})();
