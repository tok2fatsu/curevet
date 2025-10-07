document.addEventListener("DOMContentLoaded", () => {
  // --- MOBILE NAVIGATION TOGGLE ---
  const mobileToggle = document.getElementById('mobileToggle');
  const navLinks = document.getElementById('navLinks');
  if (mobileToggle && navLinks) {
    mobileToggle.addEventListener('click', () => {
      navLinks.classList.toggle('active');
    });

    document.querySelectorAll('.nav-links a').forEach(link => {
      link.addEventListener('click', () => {
        navLinks.classList.remove('active');
      });
    });
  }

  // --- SMOOTH SCROLLING ---
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
      e.preventDefault();
      const targetId = this.getAttribute('href');
      if (targetId === '#') return;

      const targetElement = document.querySelector(targetId);
      if (targetElement) {
        window.scrollTo({
          top: targetElement.offsetTop - 80,
          behavior: 'smooth'
        });
      }
    });
  });

  // --- UPDATE YEAR IN FOOTER ---
  const yearEl = document.getElementById('year');
  if (yearEl) yearEl.textContent = new Date().getFullYear();

  // --- BOOKING FORM LOGIC ---
  const form = document.getElementById("contactForm");
  const dateInput = document.getElementById("booking_date");
  const timeSelect = document.getElementById("booking_time");

  const alertBox = document.getElementById("formAlert");

  function showAlert(message, type = "error") {
    if (!alertBox) return alert(message);
    alertBox.textContent = message;
    alertBox.className = type === "success" ? "alert success" : "alert error";
    alertBox.style.display = "block";
  }

  function clearAlert() {
    if (alertBox) alertBox.style.display = "none";
  }

  // Fetch available time slots
  async function loadAvailableSlots(date) {
    clearAlert();
    if (!date) {
      showAlert("Please select a date first.");
      return;
    }

    if (!timeSelect) {
      console.error("Missing #booking_time element");
      return;
    }

    timeSelect.innerHTML = '<option value="">Loading...</option>';

    try {
      const response = await fetch(`/backend/api/contacts.php?action=availableSlots&date=${encodeURIComponent(date)}`, {
        method: "GET",
        headers: { "Accept": "application/json" }
      });

      if (!response.ok) {
        showAlert(`Server error (${response.status}) while fetching slots.`);
        timeSelect.innerHTML = '<option value="">Error loading slots</option>';
        return;
      }

      const data = await response.json();
      console.log("Slots response:", data);

      timeSelect.innerHTML = '<option value="">Select a time</option>';

      if (data.success && Array.isArray(data.slots) && data.slots.length > 0) {
        data.slots.forEach(slot => {
          const option = document.createElement("option");
          option.value = slot;
          option.textContent = slot;
          timeSelect.appendChild(option);
        });
      } else {
        const option = document.createElement("option");
        option.value = "";
        option.textContent = "No available slots";
        option.disabled = true;
        timeSelect.appendChild(option);
      }
    } catch (err) {
      console.error("Error loading slots:", err);
      showAlert("Network error while loading available slots.");
      timeSelect.innerHTML = '<option value="">Network error</option>';
    }
  }

  // Watch date input
  if (dateInput) {
    dateInput.addEventListener("change", () => {
      const selectedDate = dateInput.value;
      clearAlert();
      if (selectedDate) loadAvailableSlots(selectedDate);
      else timeSelect.innerHTML = '<option value="">Select a time</option>';
    });
  }

  // Handle form submission
  if (form) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      clearAlert();

      document.querySelectorAll(".error").forEach(el => (el.style.display = "none"));

      const formData = new FormData(form);

      try {
        const response = await fetch(form.action, {
          method: "POST",
          body: formData,
        });

        if (!response.ok) {
          showAlert("Server error. Please try again later.");
          return;
        }

        const data = await response.json();
        console.log("Form submit response:", data);

        if (data.success) {
          showAlert(`Booking successful! Your Booking ID: ${data.booking_id}`, "success");
          form.reset();
          if (timeSelect) timeSelect.innerHTML = '<option value="">Select a time</option>';
        } else {
          if (data.errors) {
            data.errors.forEach(err => {
              if (err.includes("name")) document.getElementById("name-error").style.display = "block";
              if (err.includes("email")) document.getElementById("email-error").style.display = "block";
              if (err.includes("date")) document.getElementById("date-error").style.display = "block";
              if (err.includes("time")) document.getElementById("time-error").style.display = "block";
              if (err.includes("message")) document.getElementById("message-error").style.display = "block";
              if (err.includes("consent")) document.getElementById("consent-error").style.display = "block";
            });
          }
          showAlert("Please correct the highlighted errors and try again.");
        }
      } catch (err) {
        console.error("Submission error:", err);
        showAlert("Network error. Try again.");
      }
    });
  }

  // --- SCROLL ANIMATIONS ---
  const glassCards = document.querySelectorAll('.glass-card');
  const observerOptions = { threshold: 0.1, rootMargin: '0px 0px -50px 0px' };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.opacity = 1;
        entry.target.style.transform = 'translateY(0)';
      }
    });
  }, observerOptions);

  glassCards.forEach(card => {
    card.style.opacity = 0;
    card.style.transform = 'translateY(20px)';
    card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
    observer.observe(card);
  });

  // --- PARALLAX HERO ---
  const hero = document.querySelector('.hero');
  if (hero) {
    window.addEventListener('scroll', () => {
      hero.style.backgroundPositionY = window.pageYOffset * 0.5 + 'px';
    });
  }
});
