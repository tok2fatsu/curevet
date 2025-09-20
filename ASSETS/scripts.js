 document.addEventListener("DOMContentLoaded", () => {
  // --- Mobile Navigation Toggle ---
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

  // --- Smooth scrolling ---
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
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

  // --- Update year in footer ---
  const yearEl = document.getElementById('year');
  if (yearEl) yearEl.textContent = new Date().getFullYear();

  // --- Booking Form ---
document.addEventListener("DOMContentLoaded", () => {
  const contactForm = document.getElementById("contactForm");
  const dateInput = document.getElementById("booking_date");
  const timeSelect = document.getElementById("booking_time");
  const consentCheckbox = document.querySelector('input[name="consent"]');

  // --- Ensure min date is today ---
  const today = new Date();
  const yyyy = today.getFullYear();
  const mm = String(today.getMonth() + 1).padStart(2, '0');
  const dd = String(today.getDate()).padStart(2, '0');
  dateInput.setAttribute('min', `${yyyy}-${mm}-${dd}`);

  // --- Fetch slots from backend ---
  async function loadAvailableSlots(date) {
    try {
      const res = await fetch(`/backend/api/contacts.php?action=availableSlots&date=${date}`);
      const data = await res.json();
      timeSelect.innerHTML = '<option value="">Select a time</option>';

      if (data.success && data.slots.length > 0) {
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
    }
  }

  dateInput.addEventListener("change", () => {
    if (dateInput.value) {
      loadAvailableSlots(dateInput.value);
    }
  });

  // --- Validation ---
  function validateForm() {
    let isValid = true;

    const fields = [
      { id: "name", regex: /.+/ },
      { id: "email", regex: /^[^\s@]+@[^\s@]+\.[^\s@]+$/ },
      { id: "booking_date", regex: /.+/ },
      { id: "booking_time", regex: /.+/ },
      { id: "message", regex: /.+/ }
    ];

    fields.forEach(field => {
      const input = document.getElementById(field.id);
      const errorEl = document.getElementById(`${field.id}-error`);
      if (!field.regex.test(input.value.trim())) {
        errorEl.style.display = "block";
        isValid = false;
      } else {
        errorEl.style.display = "none";
      }
    });

    if (!consentCheckbox.checked) {
      document.getElementById("consent-error").style.display = "block";
      isValid = false;
    } else {
      document.getElementById("consent-error").style.display = "none";
    }

    return isValid;
  }

  // --- Submit handler ---
  if (contactForm) {
    contactForm.addEventListener("submit", async (e) => {
      e.preventDefault();

      if (!validateForm()) return;

      const formData = new FormData(contactForm);

      try {
        const res = await fetch(contactForm.action, {
          method: "POST",
          body: formData
        });
        const data = await res.json();

        if (data.success) {
          alert(`Booking successful! Your Booking ID: ${data.booking_id}`);
          contactForm.reset();
          timeSelect.innerHTML = '<option value="">Select a time</option>';
        } else {
          alert("Error: " + (data.errors ? data.errors.join(", ") : "Unknown error"));
        }
      } catch (err) {
        console.error("Submission error:", err);
        alert("Network error. Try again.");
      }
    });
  }
});


  // --- Scroll animations ---
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

  // --- Parallax hero ---
  const hero = document.querySelector('.hero');
  if (hero) {
    window.addEventListener('scroll', () => {
      hero.style.backgroundPositionY = window.pageYOffset * 0.5 + 'px';
    });
  }
});
