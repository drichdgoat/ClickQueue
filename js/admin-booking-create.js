(function () {
  "use strict";

  const $ = (id) => document.getElementById(id);
  const form = $("createBookingForm");
  if (!form) return;

  const modal = $("createBookingModal");
  const openButton = $("addBookingBtn");
  const serviceSelect = $("createServiceType");
  const packageSelect = $("createPackage");
  const details = $("createPackageDetails");
  const summary = $("createPriceSummary");
  const submitButton = $("createBookingSubmit");
  const message = $("createBookingMessage");
  const paymentSelect = $("createPaymentMethod");
  const proofInput = $("createProof");
  const eventDate = $("createEventDate");
  const calendarGrid = $("createCalendarGrid");
  const calendarMessage = $("createAvailabilityMessage");
  const calendarPrev = $("createCalendarPrev");
  const calendarNext = $("createCalendarNext");
  const monthNames = [
    "January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December",
  ];
  let catalog = null;
  let priceRequest = 0;
  let availabilityRequest = 0;
  let bookedRanges = [];
  let availabilityLoaded = false;
  let availabilityError = false;
  let availabilityErrorMessage = "";
  let calendarYear = new Date().getFullYear();
  let calendarMonth = new Date().getMonth();

  function esc(value) {
    return String(value == null ? "" : value).replace(/[&<>"']/g, function (char) {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      }[char];
    });
  }

  function peso(value) {
    return "\u20B1" + Number(value || 0).toLocaleString("en-US", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }

  function selectedPackage() {
    if (!catalog || !packageSelect.value) return null;
    const items = serviceSelect.value === "camera_rental" ? catalog.cameras : catalog.photobooth;
    const key = serviceSelect.value === "camera_rental" ? "camera_id" : "photobooth_id";
    return items.find((item) => Number(item[key]) === Number(packageSelect.value)) || null;
  }

  function bookingField(name) {
    return details.querySelector('[data-booking-field="' + name + '"]');
  }

  function rentalDurationDays() {
    const item = selectedPackage();
    return serviceSelect.value === "camera_rental" && item && item.pricing_type !== "per_photo"
      ? Math.max(1, Number((bookingField("days") || {}).value) || 1)
      : 1;
  }

  function formatDate(value) {
    if (!value) return "";
    const [year, month, day] = value.split("-").map(Number);
    return new Date(year, month - 1, day).toLocaleDateString("en-US", {
      year: "numeric",
      month: "short",
      day: "numeric",
    });
  }

  function dateAfterDays(value, days) {
    const [year, month, day] = value.split("-").map(Number);
    const date = new Date(year, month - 1, day);
    date.setDate(date.getDate() + days - 1);
    return [
      date.getFullYear(),
      String(date.getMonth() + 1).padStart(2, "0"),
      String(date.getDate()).padStart(2, "0"),
    ].join("-");
  }

  function todayISO() {
    const today = new Date();
    return [
      today.getFullYear(),
      String(today.getMonth() + 1).padStart(2, "0"),
      String(today.getDate()).padStart(2, "0"),
    ].join("-");
  }

  function isDateAvailable(startDate) {
    if (!availabilityLoaded) return false;
    const endDate = dateAfterDays(startDate, rentalDurationDays());
    return !bookedRanges.some((range) =>
      range.start_date <= endDate && range.end_date >= startDate
    );
  }

  function drawAvailabilityCalendar() {
    const item = selectedPackage();
    const hasPackage = !!item;
    const firstDay = new Date(calendarYear, calendarMonth, 1).getDay();
    const daysInMonth = new Date(calendarYear, calendarMonth + 1, 0).getDate();
    const today = todayISO();
    let html = "";
    $("createCalendarTitle").textContent = monthNames[calendarMonth] + " " + calendarYear;
    calendarPrev.disabled = !hasPackage;
    calendarNext.disabled = !hasPackage;
    for (let i = 0; i < firstDay; i++) html += "<span></span>";
    for (let day = 1; day <= daysInMonth; day++) {
      const month = String(calendarMonth + 1).padStart(2, "0");
      const date = calendarYear + "-" + month + "-" + String(day).padStart(2, "0");
      const past = date < today;
      const booked = availabilityLoaded && hasPackage && !isDateAvailable(date);
      const selected = date === eventDate.value;
      const classes = [
        "booking-calendar-day",
        past ? "is-past" : "",
        booked ? "is-booked" : availabilityLoaded && hasPackage && !past ? "is-available" : "",
        selected ? "is-selected" : "",
        hasPackage && !availabilityLoaded ? "is-loading" : "",
      ].filter(Boolean).join(" ");
      const disabled = !hasPackage || !availabilityLoaded || availabilityError || past || booked;
      const stateLabel = booked ? "booked" : past ? "past" : "available";
      html += '<button type="button" class="' + classes + '" data-date="' + date +
        '" aria-label="' + formatDate(date) + ", " + stateLabel + '"' +
        (selected ? ' aria-pressed="true"' : ' aria-pressed="false"') +
        (disabled ? " disabled" : "") + ">" + day + "</button>";
    }
    calendarGrid.innerHTML = html;
    $("createEventDateDisplay").value = eventDate.value ? formatDate(eventDate.value) : "";

    calendarMessage.classList.toggle("booking-calendar-message", true);
    calendarMessage.classList.toggle("is-error", availabilityError);
    if (!hasPackage) {
      calendarMessage.textContent = "Choose a service and package to view availability.";
    } else if (availabilityError) {
      calendarMessage.textContent = availabilityErrorMessage + " Try another month or select the package again.";
    } else if (!availabilityLoaded) {
      calendarMessage.textContent = "Loading availability...";
    } else {
      calendarMessage.textContent = "Select an available date.";
    }
  }

  async function loadAvailability() {
    const item = selectedPackage();
    const requestId = ++availabilityRequest;
    bookedRanges = [];
    availabilityLoaded = false;
    availabilityError = false;
    drawAvailabilityCalendar();
    if (!item) return;

    const packageId = serviceSelect.value === "camera_rental" ? item.camera_id : item.photobooth_id;
    const params = new URLSearchParams({
      service_type: serviceSelect.value,
      package_id: String(packageId),
      days: String(rentalDurationDays()),
      year: String(calendarYear),
      month: String(calendarMonth + 1),
    });
    try {
      const response = await fetch("../../api/public/availability.php?" + params.toString());
      let data;
      try {
        data = await response.json();
      } catch (error) {
        throw new Error("The availability service returned an invalid response.");
      }
      if (!response.ok || !Array.isArray(data.booked_ranges)) {
        throw new Error(data.error || "Could not load availability.");
      }
      if (requestId !== availabilityRequest) return;
      bookedRanges = data.booked_ranges;
      availabilityLoaded = true;
      availabilityErrorMessage = "";
      drawAvailabilityCalendar();
      if (eventDate.value && !isDateAvailable(eventDate.value)) {
        eventDate.value = "";
        $("createEventDateDisplay").value = "";
        updateTimeLabels();
        drawAvailabilityCalendar();
      }
    } catch (error) {
      if (requestId !== availabilityRequest) return;
      availabilityError = true;
      availabilityErrorMessage = error.message || "Could not load availability.";
      drawAvailabilityCalendar();
    }
  }

  function updateTimeLabels() {
    const startDate = eventDate.value;
    const endDate = startDate ? dateAfterDays(startDate, rentalDurationDays()) : "";
    $("createStartTimeLabel").textContent =
      "Start Time" + (startDate ? " (" + formatDate(startDate) + ")" : "");
    $("createEndTimeLabel").textContent =
      "End Time" + (endDate ? " (" + formatDate(endDate) + ")" : "");
  }

  function renderPackageOptions() {
    const isCamera = serviceSelect.value === "camera_rental";
    const items = serviceSelect.value === "photobooth"
      ? catalog.photobooth
      : isCamera
        ? catalog.cameras
        : [];
    packageSelect.innerHTML = '<option value="">Choose a package...</option>' +
      items.map((item) => {
        const id = isCamera ? item.camera_id : item.photobooth_id;
        return '<option value="' + esc(id) + '">' + esc(item.name) + "</option>";
      }).join("");
    packageSelect.disabled = items.length === 0;
    if (!items.length && serviceSelect.value) {
      packageSelect.innerHTML = '<option value="">No active packages available</option>';
    }
    renderPackageDetails();
  }

  function renderPackageDetails() {
    const item = selectedPackage();
    details.innerHTML = "";
    if (!item) {
      updatePriceSummary();
      return;
    }

    if (serviceSelect.value === "camera_rental") {
      let html = "";
      if (item.pricing_type === "per_day") {
        const minDays = Math.min.apply(null, item.rate_tiers.map((tier) => Number(tier.min_days)));
        html += '<div class="booking-field"><label for="createDays">Rental Duration (days)</label>' +
          '<input id="createDays" data-booking-field="days" type="number" min="' + minDays + '" value="' + minDays + '" required></div>';
      } else if (item.pricing_type === "per_day_and_hour") {
        const flatDays = Number(item.flat_hourly.flat_days);
        html += '<div class="booking-field"><label for="createDays">Rental Days</label>' +
          '<input id="createDays" data-booking-field="days" type="number" min="1" value="' + flatDays + '" required></div>' +
          '<div class="booking-field"><label for="createExtraHours">Additional Hours</label>' +
          '<input id="createExtraHours" data-booking-field="extra_hours" type="number" min="0" value="0" required></div>';
      } else if (item.pricing_type === "per_photo") {
        const minQty = Math.min.apply(null, item.photo_tiers.map((tier) => Number(tier.min_qty)));
        html += '<div class="booking-field"><label for="createPhotoQty">Number of Photos</label>' +
          '<input id="createPhotoQty" data-booking-field="photo_qty" type="number" min="' + minQty + '" value="' + minQty + '" required></div>';
      }

      if (catalog.camera_accessories.length) {
        html += '<div class="booking-option-list"><strong>Optional Accessories</strong>' +
          catalog.camera_accessories.map((accessory) =>
            '<label class="booking-option"><input type="checkbox" data-accessory-id="' +
            esc(accessory.accessory_id) + '"> ' + esc(accessory.name) + " (" + peso(accessory.price) + ")</label>"
          ).join("") + "</div>";
      }
      details.innerHTML = html;
    } else if (catalog.photobooth_addons.length) {
      details.innerHTML = catalog.photobooth_addons.map((addon) =>
        '<div class="booking-field"><label for="createAddon' + esc(addon.addon_id) + '">' +
        esc(addon.name) + " (" + peso(addon.price) + ")</label>" +
        '<input id="createAddon' + esc(addon.addon_id) + '" data-addon-id="' + esc(addon.addon_id) +
        '" type="number" min="0" max="10" value="0"></div>'
      ).join("");
    }

    updatePriceSummary();
  }

  function pricingRequestBody() {
    const item = selectedPackage();
    if (!item) return null;
    const body = { service_type: serviceSelect.value };
    if (serviceSelect.value === "camera_rental") {
      body.camera_id = Number(item.camera_id);
      const days = bookingField("days");
      const hours = bookingField("extra_hours");
      const photos = bookingField("photo_qty");
      if (days) body.days = Number(days.value);
      if (hours) body.extra_hours = Number(hours.value);
      if (photos) body.photo_qty = Number(photos.value);
      body.accessory_ids = Array.from(details.querySelectorAll("[data-accessory-id]:checked"))
        .map((input) => Number(input.dataset.accessoryId));
    } else {
      body.photobooth_id = Number(item.photobooth_id);
      body.addon_qty = {};
      details.querySelectorAll("[data-addon-id]").forEach((input) => {
        if (Number(input.value) > 0) body.addon_qty[input.dataset.addonId] = Number(input.value);
      });
    }
    return body;
  }

  async function updatePriceSummary() {
    const body = pricingRequestBody();
    const requestId = ++priceRequest;
    if (!body) {
      summary.textContent = "Select a package to see the booking total.";
      return;
    }

    summary.textContent = "Calculating booking total...";
    try {
      const response = await fetch("../../api/public/calculate.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      });
      let data;
      try {
        data = await response.json();
      } catch (error) {
        throw new Error("The pricing service returned an invalid response.");
      }
      if (requestId !== priceRequest) return;
      if (!response.ok || !data.success) {
        summary.textContent = data.error || "Could not calculate the booking total.";
        return;
      }
      summary.textContent = "Total: " + peso(data.summary.grand_total) +
        " · Due now: " + peso(data.summary.amount_due_now) +
        " · Remaining: " + peso(data.summary.remaining_balance);
    } catch (error) {
      if (requestId === priceRequest) {
        summary.textContent = "Could not calculate the booking total. Check your connection and try again.";
      }
    }
  }

  function showMessage(text, success) {
    message.textContent = text;
    message.hidden = false;
    message.classList.toggle("is-success", !!success);
  }

  function clearMessage() {
    message.textContent = "";
    message.hidden = true;
    message.classList.remove("is-success");
  }

  function updateProofRequirement() {
    const requiresProof = paymentSelect.value !== "" && paymentSelect.value !== "Over-the-Counter";
    proofInput.required = requiresProof;
    $("createProofField").hidden = !requiresProof;
    if (!requiresProof) proofInput.value = "";
  }

  function validateTimes() {
    const start = $("createStartTime");
    const end = $("createEndTime");
    const rentalDays = rentalDurationDays();
    end.setCustomValidity(rentalDays === 1 && start.value && end.value && end.value <= start.value
      ? "For a one-day booking, end time must be later than start time."
      : "");
  }

  function openModal() {
    modal.hidden = false;
    document.body.classList.add("booking-modal-open");
    loadAvailability();
    $("createFullName").focus();
  }

  function closeModal() {
    modal.hidden = true;
    document.body.classList.remove("booking-modal-open");
    openButton.focus();
  }

  openButton.addEventListener("click", openModal);
  modal.querySelectorAll("[data-booking-close]").forEach((button) => {
    button.addEventListener("click", closeModal);
  });
  modal.addEventListener("mousedown", (event) => {
    if (event.target === modal) closeModal();
  });
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !modal.hidden) closeModal();
  });

  serviceSelect.addEventListener("change", () => {
    if (!catalog) return;
    eventDate.value = "";
    $("createEventDateDisplay").value = "";
    renderPackageOptions();
    updateTimeLabels();
    loadAvailability();
    clearMessage();
  });
  packageSelect.addEventListener("change", () => {
    eventDate.value = "";
    $("createEventDateDisplay").value = "";
    renderPackageDetails();
    updateTimeLabels();
    loadAvailability();
    validateTimes();
    clearMessage();
  });
  details.addEventListener("input", () => {
    updatePriceSummary();
    updateTimeLabels();
  });
  details.addEventListener("input", (event) => {
    if (event.target.matches('[data-booking-field="days"]')) {
      eventDate.value = "";
      $("createEventDateDisplay").value = "";
      updateTimeLabels();
      loadAvailability();
    }
  });
  details.addEventListener("input", validateTimes);
  details.addEventListener("change", () => {
    updatePriceSummary();
    updateTimeLabels();
    validateTimes();
  });
  calendarPrev.addEventListener("click", () => {
    calendarMonth--;
    if (calendarMonth < 0) {
      calendarMonth = 11;
      calendarYear--;
    }
    loadAvailability();
  });
  calendarNext.addEventListener("click", () => {
    calendarMonth++;
    if (calendarMonth > 11) {
      calendarMonth = 0;
      calendarYear++;
    }
    loadAvailability();
  });
  calendarGrid.addEventListener("click", (event) => {
    const day = event.target.closest("[data-date]");
    if (!day || day.disabled || !isDateAvailable(day.dataset.date)) return;
    eventDate.value = day.dataset.date;
    $("createEventDateDisplay").value = formatDate(eventDate.value);
    updateTimeLabels();
    drawAvailabilityCalendar();
    clearMessage();
  });
  paymentSelect.addEventListener("change", updateProofRequirement);
  $("createStartTime").addEventListener("change", validateTimes);
  $("createEndTime").addEventListener("change", validateTimes);
  form.addEventListener("input", clearMessage);

  updateTimeLabels();
  drawAvailabilityCalendar();
  updateProofRequirement();

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    clearMessage();
    validateTimes();
    if (!eventDate.value || !availabilityLoaded || availabilityError || !isDateAvailable(eventDate.value)) {
      showMessage("Select an available event date before adding the booking.");
      return;
    }
    if (!form.reportValidity()) return;

    const item = selectedPackage();
    if (!catalog || !item) {
      showMessage("Select an active service package before adding the booking.");
      return;
    }

    const formData = new FormData(form);
    formData.set("agree_terms", $("createAgreeTerms").checked ? "1" : "0");
    if (serviceSelect.value === "camera_rental") {
      formData.set("camera_id", item.camera_id);
      const days = bookingField("days");
      const hours = bookingField("extra_hours");
      const photos = bookingField("photo_qty");
      if (days) formData.set("days", days.value);
      if (hours) formData.set("extra_hours", hours.value);
      if (photos) formData.set("photo_qty", photos.value);
      details.querySelectorAll("[data-accessory-id]:checked").forEach((input) => {
        formData.append("accessory_ids[]", input.dataset.accessoryId);
      });
    } else {
      formData.set("photobooth_id", item.photobooth_id);
      details.querySelectorAll("[data-addon-id]").forEach((input) => {
        if (Number(input.value) > 0) {
          formData.set("addon_qty[" + input.dataset.addonId + "]", input.value);
        }
      });
    }

    submitButton.disabled = true;
    submitButton.textContent = "Adding...";
    let bookingCreated = false;
    try {
      const response = await fetch("../../api/public/book.php", {
        method: "POST",
        body: formData,
      });
      let data;
      try {
        data = await response.json();
      } catch (error) {
        throw new Error("The booking service returned an invalid response.");
      }
      if (!response.ok || !data.success) {
        showMessage(data.error || "Could not add the booking. Please try again.");
        return;
      }
      bookingCreated = true;
      showMessage("Booking " + data.reference_no + " created. Refreshing bookings...", true);
      window.setTimeout(() => window.location.reload(), 900);
    } catch (error) {
      showMessage(error.message || "Could not reach the booking service. Check your connection and try again.");
    } finally {
      if (!bookingCreated) {
        submitButton.disabled = false;
        submitButton.textContent = "Add Booking";
      }
    }
  });

  fetch("../../api/public/packages.php")
    .then(async (response) => {
      let data;
      try {
        data = await response.json();
      } catch (error) {
        throw new Error("The package service returned an invalid response.");
      }
      if (!response.ok || data.error) {
        throw new Error(data.error || "Could not load active packages.");
      }
      if (
        !Array.isArray(data.cameras) ||
        !Array.isArray(data.camera_accessories) ||
        !Array.isArray(data.photobooth) ||
        !Array.isArray(data.photobooth_addons)
      ) {
        throw new Error("The package service returned incomplete package data.");
      }
      catalog = data;
      renderPackageOptions();
      submitButton.disabled = false;
    })
    .catch((error) => {
      submitButton.disabled = true;
      showMessage(error.message || "Could not load active packages.");
    });
})();
