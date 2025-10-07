document.addEventListener("DOMContentLoaded", () => {
  // --- Mobile Navigation Toggle ---
  const mobileToggle = document.getElementById("mobileToggle");
  const navLinks = document.getElementById("navLinks");

  if (mobileToggle && navLinks) {
    mobileToggle.addEventListener("click", () => {
      navLinks.classList.toggle("active");
    });

    document.querySelectorAll(".nav-links a").forEach(link => {
      link.addEventListener("click", () => {
        navLinks.classList.remove("active");
      });
    });
  }

  // --- Smooth scrolling ---
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener("click", function (e) {
      e.preventDefault();
      const targetId = this.getAttribute("href");
      if (targetId === "#") return;

      const targetElement = document.querySelector(targetId);
      if (targetElement) {
        window.scrollTo({
          top: targetElement.offsetTop - 80,
          behavior: "smooth"
        });
      }
    });
  });

  // --- Update year in footer ---
  const yearEl = document.getElementById("year");
  if (yearEl) yearEl.textContent = new Date().getFullYear();

  // --- Booking Form ---
  const form = document.getElementById("contactForm");
  const dateInput = document.getElementById("booking_date");
  const timeSelect = document.getElementById("booking_time");

  // Inline feedback element
  const feedbackBox = document.createElement("div");
  feedbackBox.id = "form-feedback";
  feedbackBox.style.marginTop = "1em";
  feedbackBox.style.padding = "10px";
  feedbackBox.style.borderRadius = "8px";
  feedbackBox.style.display = "none";
  feedbackBox.style.fontWeight = "500";
  if (form) form.appendChild(feedbackBox);

  // Feedback helper
  function showFeedback(message, success = true) {
    feedbackBox.textContent = message;
    feedbackBox.style.display = "block";
    feedbackBox.style.backgroundColor = success ? "#e6ffed" : "#ffe6e6";
    feedbackBox.style.color = success ? "#066b1a" : "#a10000";
    feedbackBox.style.border = success ? "1px solid #5ad36f" : "1px solid #ff6666";

    // Auto-fade after 5 seconds
    setTimeout(() => {
      feedbackBox.style.display = "none";
    }, 5000);
  }

  // --- Load available time slots ---
  async function loadAvailableSlots(date) {
    timeSelect.innerHTML = '<option value="">Loading...</option>';
    try {
      const res = await fetch(
        `/backend/api/contacts.php?action=availableSlots&date=${encodeURIComponent(date)}`
      );
      const data = await res.json();

      timeSelect.innerHTML = '<option value="">Select a time</option>';

      if (data.success && Array.isArray(data.slots)) {
        if (data.slots.length === 0) {
          const opt = document.createElement("option");
          opt.textContent = "No available slots";
          opt.disabled = true;
          timeSelect.appendChild(opt);
        } else {
          data.slots.forEach(slot => {
            const option = document.createElement("option");
            option.value = slot;
            option.textContent = slot;
            timeSelect.appendChild(option);
          });
        }
      } else {
        const opt = document.createElement("option");
        opt.textContent = "Error loading slots";
        opt.disabled = true;
        timeSelect.appendChild(opt);
      }
    } catch (err) {
      console.error("Error loading slots:", err);
      showFeedback("Could not load available times. Please try again later.", false);
    }
  }

  // --- Watch date input ---
  if (dateInput) {
    dateInput.addEventListener("change", () => {
      const selectedDate = dateInput.value;
      if (selectedDate) {
        loadAvailableSlots(selectedDate);
      } else {
        timeSelect.innerHTML = '<option value="">Select a time</option>';
      }
    });
  }

// --- Button Spinner Helpers ---
  function startLoading(btn) {
    if (!btn) return;
    btn.disabled = true;
    btn.dataset.originalText = btn.innerHTML;
    btn.innerHTML = `
      <span class="spinner" style="
        display:inline-block;
        width:1em;
        height:1em;
        border:2px solid rgba(255,255,255,0.4);
        border-top:2px solid white;
        border-radius:50%;
        animation:spin 0.8s linear infinite;
        margin-right:8px;
        vertical-align:middle;
      "></span>Submitting...
    `;
  }

  function stopLoading(btn) {
    if (!btn) return;
    btn.disabled = false;
    if (btn.dataset.originalText) {
      btn.innerHTML = btn.dataset.originalText;
    }
  }

  // --- Add spinner animation keyframes ---
  const style = document.createElement("style");
  style.textContent = `
    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }
  `;
  document.head.appendChild(style);

  // --- Handle form submission ---
  if (form) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      feedbackBox.style.display = "none";

      const formData = new FormData(form);

      try {
        const res = await fetch(form.action, { method: "POST", body: formData });
        const data = await res.json();

        if (data.success) {
          showFeedback(data.message || "Booking successful! Confirmation email sent.", true);
          form.reset();
          timeSelect.innerHTML = '<option value="">Select a time</option>';
        } else {
          showFeedback(data.message || "An error occurred. Please try again.", false);
        }
      } catch (err) {
        console.error("Submission error:", err);
        showFeedback("Network error. Please check your connection and try again.", false);
      }
    });
  }

  // --- Scroll animations ---
  const glassCards = document.querySelectorAll(".glass-card");
  const observerOptions = { threshold: 0.1, rootMargin: "0px 0px -50px 0px" };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.opacity = 1;
        entry.target.style.transform = "translateY(0)";
      }
    });
  }, observerOptions);

  glassCards.forEach(card => {
    card.style.opacity = 0;
    card.style.transform = "translateY(20px)";
    card.style.transition = "opacity 0.6s ease, transform 0.6s ease";
    observer.observe(card);
  });

  // --- Parallax hero ---
  const hero = document.querySelector(".hero");
  if (hero) {
    window.addEventListener("scroll", () => {
      hero.style.backgroundPositionY = window.pageYOffset * 0.5 + "px";
    });
  }
});

