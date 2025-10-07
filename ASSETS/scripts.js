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
  const form = document.getElementById("contactForm");
  const dateInput = document.getElementById("booking_date");
  const timeSelect = document.getElementById("booking_time");

  // Function to fetch available time slots
  async function loadAvailableSlots(date) {
    if (!date) {
      console.warn("No date provided for slot fetch");
      return;
    }

    try {
      console.log("Fetching slots for date:", date);

      const res = await fetch(`/backend/api/contacts.php?action=availableSlots&date=${encodeURIComponent(date)}`, {
        method: "GET",
        headers: { "Accept": "application/json" }
      });

      if (!res.ok) {
        console.error("HTTP error:", res.status, res.statusText);
        timeSelect.innerHTML = '<option value="">Error loading slots</option>';
        return;
      }

      let data;
      try {
        data = await res.json();
      } catch (parseErr) {
        console.error("Failed to parse JSON:", parseErr);
        timeSelect.innerHTML = '<option value="">Invalid response from server</option>';
        return;
      }

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
      timeSelect.innerHTML = '<option value="">Network or server error</option>';
    }
  }

  // Watch date input
  if (dateInput) {
    dateInput.addEventListener("change", () => {
      const selectedDate = dateInput.value;
      if (selectedDate) {
        loadAvailableSlots(selectedDate);
      } else {
        console.warn("Date input cleared, resetting slots");
        timeSelect.innerHTML = '<option value="">Select a time</option>';
      }
    });
  }

  // Form submit handler
  if (form) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      document.querySelectorAll(".error").forEach(el => el.style.display = "none");

      const formData = new FormData(form);

      try {
        const res = await fetch(form.action, {
          method: "POST",
          body: formData,
        });

        if (!res.ok) {
          console.error("Form submit HTTP error:", res.status, res.statusText);
          alert("Server error. Try again later.");
          return;
        }

        const data = await res.json();
        console.log("Form submit response:", data);

        if (data.success) {
          alert(`Booking successful! Your Booking ID: ${data.booking_id}`);
          form.reset();
          timeSelect.innerHTML = '<option value="">Select a time</option>';
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
          } else {
            alert("Something went wrong. Please try again.");
          }
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
