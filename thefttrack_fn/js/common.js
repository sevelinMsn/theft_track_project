
(function () {
  "use strict";

  const XAMPP_API_FALLBACK = "http://localhost/thefttrack/backend/";

  function getApiBase() {
    const loc = window.location;
    if (loc.protocol === "file:") {
      return XAMPP_API_FALLBACK;
    }

    const fnIdx = loc.pathname.indexOf("/thefttrack_fn");
    if (fnIdx !== -1) {
      return loc.origin + loc.pathname.substring(0, fnIdx) + "/backend/";
    }

    if (
      (loc.hostname === "localhost" || loc.hostname === "127.0.0.1") &&
      loc.port &&
      loc.port !== "80" &&
      loc.port !== "443"
    ) {
      return XAMPP_API_FALLBACK;
    }

    return "../backend/";
  }

  function isApiCrossOrigin() {
    const base = getApiBase();
    return base.indexOf("://") !== -1 && !base.startsWith(window.location.origin);
  }
  const STORAGE_USERS = "thefttrack_users";
  const STORAGE_SESSION = "thefttrack_session";
  const STORAGE_REPORTS = "thefttrack_reports";

  let currentUser = null;

  const PAGE_FILES = {
    home: "index.html",
    report: "report.html",
    track: "track.html",
    fraud: "fraud.html",
    updates: "dashboard.html",
    dashboard: "dashboard.html",
    login: "login.html",
    register: "register.html",
  };

  let pendingFileData = null;

  function getUsers() {
    try {
      return JSON.parse(localStorage.getItem(STORAGE_USERS)) || [];
    } catch {
      return [];
    }
  }

  function saveUsers(users) {
    localStorage.setItem(STORAGE_USERS, JSON.stringify(users));
  }

  function getReports() {
    try {
      return JSON.parse(localStorage.getItem(STORAGE_REPORTS)) || [];
    } catch {
      return [];
    }
  }

  function saveReports(reports) {
    localStorage.setItem(STORAGE_REPORTS, JSON.stringify(reports));
  }

  function setSession(user) {
    currentUser = user || null;
    if (user) {
      localStorage.setItem(
        STORAGE_SESSION,
        JSON.stringify({ email: user.email, fullname: user.fullname, phone: user.phone })
      );
    } else {
      localStorage.removeItem(STORAGE_SESSION);
    }
  }

  function getSession() {
    if (currentUser) return currentUser;
    try {
      return JSON.parse(localStorage.getItem(STORAGE_SESSION));
    } catch {
      return null;
    }
  }

  async function apiRequest(endpoint, options) {
    const opts = options || {};
    const headers = Object.assign({ "Content-Type": "application/json" }, opts.headers || {});
    const url = getApiBase() + endpoint.replace(/^\//, "");

    let res;
    try {
      res = await fetch(url, {
        method: opts.method || "GET",
        credentials: isApiCrossOrigin() ? "include" : "same-origin",
        headers: headers,
        body: opts.body ? JSON.stringify(opts.body) : undefined,
      });
    } catch (err) {
      if (window.location.protocol === "file:") {
        throw new Error("Open the site through XAMPP: http://localhost/thefttrack/thefttrack_fn/ (not as a local file).");
      }
      if (isApiCrossOrigin()) {
        throw new Error("Cannot reach PHP backend. Start Apache in XAMPP and use http://localhost/thefttrack/thefttrack_fn/");
      }
      throw new Error("Cannot reach server. Is Apache/MySQL running in XAMPP?");
    }

    const raw = await res.text();
    let data;
    try {
      data = raw ? JSON.parse(raw) : {};
    } catch {
      const hint = raw && raw.trim().startsWith("<") ? " Server returned HTML instead of JSON." : "";
      throw new Error("Invalid server response." + hint + " Is Apache/MySQL running?");
    }
    return data;
  }

  async function apiLoadSession() {
    try {
      const data = await apiRequest("session_user.php");
      if (data.success && data.user) {
        setSession(data.user);
      } else {
        setSession(null);
      }
    } catch {
      setSession(null);
    }
    return getSession();
  }

  function moveModalsToBody() {
    document.querySelectorAll(".modal").forEach(function (modal) {
      if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
      }
    });
  }

  function openModal(modal) {
    if (!modal) return;
    moveModalsToBody();
    modal.classList.remove("hidden");
    modal.setAttribute("aria-hidden", "false");
    modal.scrollTop = 0;
    requestAnimationFrame(function () {
      modal.classList.add("is-open");
      const dialog = modal.querySelector(".modal-dialog, .modal-content, .modal-panel");
      if (dialog) dialog.scrollTop = 0;
    });
    document.body.classList.add("modal-open");
  }

  function closeModal(modal) {
    if (!modal) return;
    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");
    setTimeout(function () {
      modal.classList.add("hidden");
      if (!document.querySelector(".modal.is-open")) {
        document.body.classList.remove("modal-open");
      }
    }, 200);
  }

  function bindModalClose(modal, attr) {
    if (!modal || modal.dataset.bound === "1") return;
    modal.dataset.bound = "1";
    const sel = attr || "[data-close-modal], [data-close-detail], .modal-backdrop, .modal-close";
    modal.querySelectorAll(sel).forEach(function (el) {
      el.addEventListener("click", function () {
        closeModal(modal);
      });
    });
  }

  function getToastContainer() {
    let el = document.getElementById("toast-container");
    if (!el) {
      el = document.createElement("div");
      el.id = "toast-container";
      el.className = "toast-container";
      el.setAttribute("aria-live", "polite");
      document.body.appendChild(el);
    }
    return el;
  }

  function showToast(message, type) {
    const toast = document.createElement("div");
    toast.className = "toast" + (type ? " " + type : "");
    toast.textContent = message;
    getToastContainer().appendChild(toast);
    setTimeout(function () {
      toast.remove();
    }, 3500);
  }

  function clearFieldError(fieldId) {
    const input = document.getElementById(fieldId);
    const errorEl = document.querySelector('[data-error="' + fieldId + '"]');
    if (input) input.classList.remove("error");
    if (errorEl) errorEl.textContent = "";
  }

  function setFieldError(fieldId, message) {
    const input = document.getElementById(fieldId);
    const errorEl = document.querySelector('[data-error="' + fieldId + '"]');
    if (input) input.classList.add("error");
    if (errorEl) errorEl.textContent = message;
  }

  function clearFormErrors(form) {
    form.querySelectorAll(".error").forEach(function (el) {
      el.classList.remove("error");
    });
    form.querySelectorAll(".form-error").forEach(function (el) {
      el.textContent = "";
    });
  }

  function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  function normalizePhone(phone) {
    return (phone || "").replace(/\D/g, "");
  }

  function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  function generateReportId() {
    const year = new Date().getFullYear();
    const chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    let code = "";
    for (let i = 0; i < 6; i++) {
      code += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return "TT-" + year + "-" + code;
  }

  function formatDate(dateStr) {
    if (!dateStr) return "—";
    const d = new Date(dateStr + "T00:00:00");
    return d.toLocaleDateString("en-US", { year: "numeric", month: "long", day: "numeric" });
  }

  function formatCategory(cat) {
    const labels = {
      electronics: "Electronics",
      vehicle: "Vehicle",
      jewelry: "Jewelry",
      documents: "Documents / ID",
      cash: "Cash",
      clothing: "Clothing",
      other: "Other",
    };
    return labels[cat] || cat;
  }

  function statusLabel(status) {
    const labels = {
      pending: "Pending",
      investigating: "Under Investigation",
      resolved: "Resolved",
    };
    return labels[status] || status;
  }

  function buildDefaultTimeline() {
    const now = new Date().toISOString();
    return [
      { title: "Report Received", message: "Your theft report was submitted and is awaiting review.", date: now, type: "info" },
      { title: "Pending Review", message: "Case assigned to review queue. You will be notified of changes.", date: null, type: "pending" },
      { title: "Under Investigation", message: "Investigation will begin once initial review is complete.", date: null, type: "investigating" },
      { title: "Case Resolved", message: "Final outcome will appear here when the case is closed.", date: null, type: "resolved" },
    ];
  }

  function getActiveTimelineIndex(status, updates) {
    if (updates && updates.length) {
      let last = 0;
      updates.forEach(function (u, i) {
        if (u.date) last = i;
      });
      return last;
    }
    if (status === "resolved") return 3;
    if (status === "investigating") return 2;
    return 1;
  }

  function goTo(pageKey, query) {
    let url = PAGE_FILES[pageKey] || PAGE_FILES.home;
    if (query) url += "?" + query;
    window.location.href = url;
  }

  function getQueryParam(name) {
    return new URLSearchParams(window.location.search).get(name);
  }

  function initNavigation() {
    const current = document.body.getAttribute("data-page") || "home";
    const session = getSession();
    const isLoggedIn = !!session;

    document.querySelectorAll(".nav-auth").forEach(function (el) {
      el.classList.toggle("hidden", isLoggedIn);
    });
    document.querySelectorAll(".nav-user").forEach(function (el) {
      el.classList.toggle("hidden", !isLoggedIn);
    });

    document.querySelectorAll("[data-nav-page]").forEach(function (link) {
      link.classList.toggle("nav-link--active", link.getAttribute("data-nav-page") === current);
    });

    const toggle = document.getElementById("nav-toggle");
    const nav = document.getElementById("main-nav");
    if (toggle && nav) {
      toggle.addEventListener("click", function () {
        const isOpen = nav.classList.toggle("open");
        toggle.setAttribute("aria-expanded", isOpen ? "true" : "false");
      });
    }

    const logoutBtn = document.getElementById("btn-logout");
    if (logoutBtn) {
      logoutBtn.addEventListener("click", function () {
        apiRequest("logout.php", { method: "POST" }).finally(function () {
          setSession(null);
          showToast("You have been logged out.");
          goTo("home");
        });
      });
    }
  }

  function bindClearErrors() {
    document.querySelectorAll("input, select, textarea").forEach(function (input) {
      input.addEventListener("input", function () {
        if (input.id) clearFieldError(input.id);
      });
    });
  }

  function initRegisterPage() {
    const form = document.getElementById("register-form");
    if (!form) return;

    form.addEventListener("submit", async function (e) {
      e.preventDefault();
      clearFormErrors(form);

      const fullname = document.getElementById("reg-fullname").value.trim();
      const email = document.getElementById("reg-email").value.trim().toLowerCase();
      const phone = document.getElementById("reg-phone").value.trim();
      const password = document.getElementById("reg-password").value;
      const confirm = document.getElementById("reg-confirm").value;
      let valid = true;

      if (!fullname) { setFieldError("reg-fullname", "Full name is required."); valid = false; }
      if (!email || !isValidEmail(email)) { setFieldError("reg-email", "Please enter a valid email."); valid = false; }
      if (!phone || normalizePhone(phone).length < 9) { setFieldError("reg-phone", "Please enter a valid phone number."); valid = false; }
      if (password.length < 6) { setFieldError("reg-password", "Password must be at least 6 characters."); valid = false; }
      if (password !== confirm) { setFieldError("reg-confirm", "Passwords do not match."); valid = false; }
      if (!valid) return;

      try {
        const data = await apiRequest("register.php", {
          method: "POST",
          body: { fullname, email, phone, password, confirm },
        });
        if (!data.success) {
          if (data.message && data.message.toLowerCase().indexOf("email") >= 0) {
            setFieldError("reg-email", data.message);
          } else {
            showToast(data.message || "Registration failed.", "error");
          }
          return;
        }
        setSession(data.user);
        showToast("Account created successfully!", "success");
        setTimeout(function () { goTo("report"); }, 600);
      } catch (err) {
        showToast(err.message || "Could not reach server.", "error");
      }
    });
  }

  function initLoginPage() {
    const form = document.getElementById("login-form");
    if (!form) return;

    form.addEventListener("submit", async function (e) {
      e.preventDefault();
      clearFormErrors(form);

      const identifier = document.getElementById("login-identifier").value.trim();
      const password = document.getElementById("login-password").value;
      let valid = true;

      if (!identifier) { setFieldError("login-identifier", "Email or phone is required."); valid = false; }
      if (!password) { setFieldError("login-password", "Password is required."); valid = false; }
      if (!valid) return;

      try {
        const data = await apiRequest("login.php", {
          method: "POST",
          body: { identifier, password },
        });
        if (!data.success) {
          setFieldError("login-password", "Invalid credentials.");
          showToast(data.message || "Login failed.", "error");
          return;
        }
        setSession(data.user);
        showToast("Welcome back, " + data.user.fullname.split(" ")[0] + "!", "success");
        setTimeout(function () { goTo("dashboard"); }, 600);
      } catch (err) {
        showToast(err.message || "Could not reach server.", "error");
      }
    });
  }

  function initReportPage() {
    const session = getSession();
    const banner = document.getElementById("report-guest-banner");
    const hint = document.getElementById("report-mode-hint");
    const fullnameEl = document.getElementById("report-fullname");
    const phoneEl = document.getElementById("report-phone");
    const emailEl = document.getElementById("report-email");
    const form = document.getElementById("report-form");
    const fileInput = document.getElementById("report-file");
    const filePreview = document.getElementById("file-preview");
    const modal = document.getElementById("report-modal");
    const modalId = document.getElementById("modal-report-id");

    if (session) {
      if (banner) banner.classList.add("hidden");
      if (hint) hint.textContent = "Signed in as " + session.fullname + ". Your report will be linked to your account.";
      if (fullnameEl && !fullnameEl.value) fullnameEl.value = session.fullname;
      if (phoneEl && !phoneEl.value) phoneEl.value = session.phone || "";
      if (emailEl && !emailEl.value) emailEl.value = session.email || "";
    }

    if (fileInput) {
      fileInput.addEventListener("change", function () {
        const file = fileInput.files[0];
        pendingFileData = null;
        if (filePreview) {
          filePreview.classList.add("hidden");
          filePreview.innerHTML = "";
        }
        if (!file) return;
        if (file.size > 5 * 1024 * 1024) {
          showToast("File must be 5MB or smaller.", "error");
          fileInput.value = "";
          return;
        }
        pendingFileData = { name: file.name, type: file.type, size: file.size };
        filePreview.classList.remove("hidden");
        if (file.type.startsWith("image/")) {
          const reader = new FileReader();
          reader.onload = function (ev) {
            filePreview.innerHTML = '<img src="' + ev.target.result + '" alt="Preview"><p>' + escapeHtml(file.name) + "</p>";
          };
          reader.readAsDataURL(file);
        } else {
          filePreview.innerHTML = '<p class="file-doc">' + escapeHtml(file.name) + "</p>";
        }
      });
    }

    const incidentDate = document.getElementById("incident-date");
    if (incidentDate) incidentDate.max = new Date().toISOString().split("T")[0];

    if (form) {
      form.addEventListener("submit", async function (e) {
        e.preventDefault();
        clearFormErrors(form);

        const fullname = document.getElementById("report-fullname").value.trim();
        const phone = document.getElementById("report-phone").value.trim();
        const email = document.getElementById("report-email").value.trim();
        const category = document.getElementById("item-category").value;
        const itemName = document.getElementById("item-name").value.trim();
        const description = document.getElementById("item-description").value.trim();
        const location = document.getElementById("incident-location").value.trim();
        const incidentDateVal = document.getElementById("incident-date").value;
        const suspect = document.getElementById("suspect-info").value.trim();
        let valid = true;

        if (!fullname) { setFieldError("report-fullname", "Full name is required."); valid = false; }
        if (!phone || normalizePhone(phone).length < 9) { setFieldError("report-phone", "A valid phone number is required."); valid = false; }
        if (email && !isValidEmail(email)) { setFieldError("report-email", "Please enter a valid email."); valid = false; }
        if (!category) { setFieldError("item-category", "Please select a category."); valid = false; }
        if (!itemName) { setFieldError("item-name", "Item name is required."); valid = false; }
        if (!description) { setFieldError("item-description", "Description is required."); valid = false; }
        if (!location) { setFieldError("incident-location", "Location is required."); valid = false; }
        if (!incidentDateVal) { setFieldError("incident-date", "Date of theft is required."); valid = false; }
        if (!valid) return;

        try {
          const data = await apiRequest("submit_report.php", {
            method: "POST",
            body: {
              fullname, phone, email, category, itemName, description,
              location, incidentDate: incidentDateVal, suspect,
            },
          });
          if (!data.success) {
            showToast(data.message || "Could not submit report.", "error");
            return;
          }
          const reportId = data.tracking_id || (data.report && data.report.id);
          form.reset();
          pendingFileData = null;
          if (filePreview) { filePreview.classList.add("hidden"); filePreview.innerHTML = ""; }
          if (modalId) modalId.textContent = reportId;
          if (modal) {
            bindModalClose(modal);
            openModal(modal);
          }
          showToast("Report submitted! Save your Tracking ID.", "success");
        } catch (err) {
          showToast(err.message || "Could not reach server.", "error");
        }
      });
    }

    if (modal) bindModalClose(modal);

    const trackBtn = document.getElementById("modal-track-btn");
    if (trackBtn) {
      trackBtn.addEventListener("click", function () {
        const id = modalId ? modalId.textContent : "";
        goTo("track", "id=" + encodeURIComponent(id));
      });
    }

    const copyBtn = document.getElementById("btn-copy-id");
    if (copyBtn) {
      copyBtn.addEventListener("click", function () {
        const id = modalId ? modalId.textContent : "";
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(id).then(function () { showToast("Tracking ID copied!", "success"); });
        } else {
          showToast("Tracking ID: " + id);
        }
      });
    }
  }

  function buildStatusSteps(status) {
    const steps = [
      { label: "Pending" },
      { label: "Under Investigation" },
      { label: "Resolved" },
    ];
    const order = { pending: 0, investigating: 1, resolved: 2 };
    const current = order[status] !== undefined ? order[status] : 0;
    return (
      '<div class="status-steps">' +
      steps.map(function (step, i) {
        const state = i < current ? "done" : i === current ? "active" : "";
        return '<div class="status-step ' + state + '"><span class="status-step-dot"></span><span>' + step.label + "</span></div>";
      }).join("") +
      "</div>"
    );
  }

  function buildTimelineHtml(updates, status) {
    const activeIdx = getActiveTimelineIndex(status, updates);
    return (
      '<div class="notifications-section"><h3>Notifications & Updates</h3><div class="timeline">' +
      (updates || []).map(function (item, index) {
        const isActive = index <= activeIdx;
        const dateStr = item.date
          ? new Date(item.date).toLocaleString("en-US", { month: "short", day: "numeric", year: "numeric", hour: "2-digit", minute: "2-digit" })
          : "Awaiting";
        return (
          '<div class="timeline-item' + (isActive ? " active" : "") + '">' +
          '<span class="timeline-dot"></span><div class="timeline-content">' +
          "<strong>" + escapeHtml(item.title) + "</strong><p>" + escapeHtml(item.message) + '</p>' +
          '<span class="timeline-date">' + dateStr + "</span></div></div>"
        );
      }).join("") +
      "</div></div>"
    );
  }

  function detailRow(label, value) {
    return '<div class="track-detail-row"><span class="label">' + escapeHtml(label) + "</span><span>" + escapeHtml(String(value)) + "</span></div>";
  }

  function buildTrackResultHtml(report) {
    return (
      '<div class="track-result-inner">' +
      '<div class="track-result-header"><div><h2>' + escapeHtml(report.itemName) + '</h2><p class="track-id-label">Tracking ID: <code>' + escapeHtml(report.id) + "</code></p></div>" +
      '<span class="status-badge status-' + report.status + '">' + statusLabel(report.status) + "</span></div>" +
      buildStatusSteps(report.status) +
      '<div class="track-details card">' +
      detailRow("Reporter", report.reporterName) + detailRow("Phone", report.phone) +
      (report.email ? detailRow("Email", report.email) : "") +
      detailRow("Category", formatCategory(report.category)) + detailRow("Description", report.description) +
      detailRow("Location", report.location) + detailRow("Date of Theft", formatDate(report.incidentDate)) +
      (report.suspect ? detailRow("Suspect Info", report.suspect) : "") +
      (report.attachment ? detailRow("Attachment", report.attachment.name) : "") +
      detailRow("Filed On", new Date(report.createdAt).toLocaleString("en-US")) +
      "</div>" + buildTimelineHtml(report.updates, report.status) + "</div>"
    );
  }

  async function searchReport(reportId, verifyPhone) {
    const id = (reportId || "").trim().toUpperCase();
    const phoneInput = (verifyPhone || "").trim();
    const resultEl = document.getElementById("track-result");
    if (!resultEl) return;

    clearFieldError("track-id");
    if (!id) {
      setFieldError("track-id", "Please enter a Tracking ID.");
      resultEl.classList.add("hidden");
      return;
    }

    resultEl.classList.remove("hidden");
    resultEl.innerHTML = '<p class="empty-state">Searching…</p>';
    let report = null;
    try {
      const data = await apiRequest(
        "track_report.php?tracking_id=" + encodeURIComponent(id) +
        (phoneInput ? "&phone=" + encodeURIComponent(phoneInput) : "")
      );
      if (data.success && data.report) {
        report = data.report;
      } else if (data.phone_mismatch) {
        resultEl.innerHTML = '<div class="track-not-found card"><p><strong>Phone verification failed</strong></p><p>The phone number does not match this report.</p></div>';
        return;
      }
    } catch (err) {
      resultEl.innerHTML = '<div class="track-not-found card"><p><strong>Error</strong></p><p>' + escapeHtml(err.message || "Could not reach server.") + "</p></div>";
      return;
    }
    if (!report) {
      resultEl.innerHTML = '<div class="track-not-found card"><p><strong>Case not found</strong></p><p>No report exists with ID <code>' + escapeHtml(id) + "</code>.</p></div>";
      return;
    }

    resultEl.innerHTML = buildTrackResultHtml(report);
  }

  function initTrackPage() {
    const form = document.getElementById("track-form");
    const idInput = document.getElementById("track-id");
    const urlId = getQueryParam("id");

    if (urlId && idInput) {
      idInput.value = urlId;
      searchReport(urlId, "");
    }

    if (form) {
      form.addEventListener("submit", function (e) {
        e.preventDefault();
        searchReport(document.getElementById("track-id").value, document.getElementById("track-phone").value);
      });
    }
  }

  function openReportDetailModal(report) {
    const modal = document.getElementById("report-detail-modal");
    const body = document.getElementById("report-detail-body");
    const title = document.getElementById("detail-modal-title");
    if (!modal || !body) {
      goTo("track", "id=" + encodeURIComponent(report.id));
      return;
    }
    if (title) title.textContent = "Report — " + (report.itemName || report.id);
    body.innerHTML = buildTrackResultHtml(report);
    bindModalClose(modal, "[data-close-detail], .modal-backdrop, .modal-close");
    openModal(modal);
  }

  async function initDashboardPage() {
    const guest = document.getElementById("dashboard-guest");
    const app = document.getElementById("dashboard-app");
    const session = getSession();

    if (!session) {
      if (guest) guest.classList.remove("hidden");
      if (app) app.classList.add("hidden");
      return;
    }

    if (guest) guest.classList.add("hidden");
    if (app) app.classList.remove("hidden");

    const welcome = document.getElementById("dashboard-welcome");
    if (welcome) welcome.textContent = "Welcome back, " + session.fullname.split(" ")[0];

    try {
      const data = await apiRequest("user_dashboard.php");
      if (!data.success) {
        showToast(data.message || "Could not load dashboard.", "error");
        return;
      }

      const profile = data.profile || {};
      const stats = data.stats || {};
      const reports = data.reports || [];
      const notifications = data.notifications || [];

      const profileEl = document.getElementById("dash-profile");
      if (profileEl) {
        profileEl.innerHTML =
          '<div class="dash-profile-item"><span>Full name</span><strong>' + escapeHtml(profile.fullname) + "</strong></div>" +
          '<div class="dash-profile-item"><span>Email</span><strong>' + escapeHtml(profile.email) + "</strong></div>" +
          '<div class="dash-profile-item"><span>Phone</span><strong>' + escapeHtml(profile.phone) + "</strong></div>" +
          '<div class="dash-profile-item"><span>Member since</span><strong>' + (profile.member_since ? formatDate(String(profile.member_since).split(" ")[0]) : "—") + "</strong></div>";
      }

      const statsEl = document.getElementById("dash-stats");
      if (statsEl) {
        statsEl.innerHTML =
          statCard("Total Reports", stats.total, "") +
          statCard("Pending", stats.pending, "pending") +
          statCard("Under Investigation", stats.investigating, "investigating") +
          statCard("Resolved", stats.resolved, "resolved");
      }

      document.getElementById("reports-count").textContent = String(reports.length);

      const listEl = document.getElementById("dash-reports-list");
      if (reports.length === 0) {
        listEl.innerHTML = '<p class="empty-state" style="padding:1.25rem">No reports yet. <a href="report.html">File a theft report</a>.</p>';
      } else {
        listEl.innerHTML = reports.map(function (r) {
          return (
            '<article class="dash-report-row">' +
            '<div><h3>' + escapeHtml(r.itemName) + '</h3>' +
            '<p class="dash-report-meta"><code>' + escapeHtml(r.id) + "</code> · " + formatDate(r.incidentDate) + " · " + escapeHtml(r.location) + "</p></div>" +
            '<div class="dash-report-actions">' +
            '<span class="status-badge status-' + r.status + '">' + statusLabel(r.status) + "</span>" +
            '<button type="button" class="btn btn-secondary btn-sm" data-view-report="' + escapeHtml(r.id) + '">View Details</button>' +
            '<a href="track.html?id=' + encodeURIComponent(r.id) + '" class="btn btn-outline btn-sm">Track</a>' +
            "</div></article>"
          );
        }).join("");

        listEl.querySelectorAll("[data-view-report]").forEach(function (btn) {
          btn.addEventListener("click", async function () {
            const id = btn.getAttribute("data-view-report");
            try {
              const detail = await apiRequest("report_detail.php?tracking_id=" + encodeURIComponent(id));
              if (detail.success && detail.report) openReportDetailModal(detail.report);
              else showToast(detail.message || "Could not load report.", "error");
            } catch (err) {
              showToast(err.message, "error");
            }
          });
        });
      }

      const notifEl = document.getElementById("dash-notifications");
      if (notifications.length === 0) {
        notifEl.innerHTML = '<p class="empty-state" style="padding:1.25rem">No new updates yet.</p>';
      } else {
        notifEl.innerHTML = notifications.map(function (n) {
          return (
            '<div class="dash-notif-item">' +
            "<strong>" + escapeHtml(n.title) + " — " + escapeHtml(n.itemName) + "</strong>" +
            "<p>" + escapeHtml(n.message) + "</p>" +
            "<time>" + escapeHtml(n.date) + " · " + escapeHtml(n.tracking_id) + "</time></div>"
          );
        }).join("");
      }
    } catch (err) {
      showToast(err.message || "Could not reach server.", "error");
    }
  }

  function statCard(label, num, cls) {
    return '<div class="dash-stat-card ' + cls + '"><div class="num">' + (num || 0) + '</div><div class="label">' + escapeHtml(label) + "</div></div>";
  }

  async function initUpdatesPage() {
    if (document.body.getAttribute("data-page") === "updates") {
      window.location.replace(PAGE_FILES.dashboard);
      return;
    }
    const session = getSession();
    const content = document.getElementById("updates-content");
    const subtitle = document.getElementById("updates-subtitle");
    if (!content) return;

    if (!session) {
      content.innerHTML = '<p class="empty-state">Please <a href="' + PAGE_FILES.login + '">sign in</a> to view updates on your reports.</p>';
      return;
    }

    if (subtitle) subtitle.textContent = "Updates for reports linked to " + session.email;

    content.innerHTML = '<p class="empty-state">Loading your reports…</p>';

    let userReports = [];
    try {
      const data = await apiRequest("my_reports.php");
      userReports = data.reports || [];
    } catch (err) {
      content.innerHTML = '<p class="empty-state">' + escapeHtml(err.message || "Could not load reports.") + "</p>";
      return;
    }

    if (userReports.length === 0) {
      content.innerHTML = '<p class="empty-state">No reports yet. <a href="' + PAGE_FILES.report + '">File a theft report</a> to get started.</p>';
      return;
    }

    const sorted = userReports.slice().sort(function (a, b) { return new Date(b.createdAt) - new Date(a.createdAt); });

    content.innerHTML = sorted.map(function (report) {
      return (
        '<article class="update-card card">' +
        '<div class="update-card-header"><h3>' + escapeHtml(report.itemName) + '</h3>' +
        '<span class="status-badge status-' + report.status + '">' + statusLabel(report.status) + "</span></div>" +
        '<p class="update-meta"><code>' + escapeHtml(report.id) + "</code> · " + formatDate(report.incidentDate) + "</p>" +
        buildTimelineHtml(report.updates, report.status) +
        '<a href="' + PAGE_FILES.track + "?id=" + encodeURIComponent(report.id) + '" class="btn btn-secondary btn-sm">View Full Case</a>' +
        "</article>"
      );
    }).join("");
  }

  function riskBadgeHtml(risk) {
    const label = risk.charAt(0).toUpperCase() + risk.slice(1);
    return '<span class="risk-badge risk-' + escapeHtml(risk) + '">' + escapeHtml(label) + " Risk</span>";
  }

  function sourceTagHtml(source) {
    if (source === "reports") {
      return '<span class="fraud-source-tag fraud-source-tag--live">From reports</span>';
    }
    if (source === "sample") {
      return '<span class="fraud-source-tag">Sample</span>';
    }
    return "";
  }

  function fraudEmptyState(message, linkHref, linkText) {
    let html = '<p class="empty-state">' + escapeHtml(message);
    if (linkHref && linkText) {
      html += ' <a href="' + escapeHtml(linkHref) + '">' + escapeHtml(linkText) + "</a>";
    }
    return html + "</p>";
  }

  function renderFraudAlerts(alerts) {
    const el = document.getElementById("fraud-alerts-grid");
    if (!el) return;
    if (!alerts.length) {
      el.innerHTML = fraudEmptyState("No reports in the database yet.", "report.html", "File the first report");
      return;
    }
    el.innerHTML = alerts.map(function (alert) {
      const title = alert.category ? escapeHtml(alert.type) + " <span class=\"fraud-alert-cat\">(" + escapeHtml(alert.category) + ")</span>" : escapeHtml(alert.type);
      const trackLink = alert.tracking_id
        ? '<a href="track.html?id=' + encodeURIComponent(alert.tracking_id) + '" class="fraud-track-link">Track ' + escapeHtml(alert.tracking_id) + "</a>"
        : "";
      return (
        '<article class="fraud-card fraud-alert-card"><div class="fraud-alert-card__header"><h3 class="fraud-alert-card__title">' + title + "</h3>" + riskBadgeHtml(alert.risk) + "</div>" +
        '<p class="fraud-alert-card__desc">' + escapeHtml(alert.description) + "</p>" +
        '<div class="fraud-alert-card__footer">' +
        '<span class="fraud-meta-item"><span class="meta-icon" aria-hidden="true">L</span>' + escapeHtml(alert.location) + "</span>" +
        '<span class="fraud-meta-item"><span class="meta-icon" aria-hidden="true">D</span>' + formatDate(alert.date) + "</span>" +
        trackLink + "</div></article>"
      );
    }).join("");
  }

  function renderHighRiskAreas(areas) {
    const el = document.getElementById("high-risk-grid");
    if (!el) return;
    if (!areas.length) {
      el.innerHTML = fraudEmptyState("No location data yet. Locations appear after reports are submitted.", "report.html", "Report theft");
      return;
    }
    el.innerHTML = areas.map(function (area) {
      return (
        '<article class="fraud-card area-card"><div class="area-card-icon">&#9673;</div><h3>' + escapeHtml(area.name) + '</h3><p class="area-count">' + area.count + '</p><p class="area-count-label">' + (area.count === 1 ? "report at this location" : "reports at this location") + "</p>" +
        riskBadgeHtml(area.risk) + "</article>"
      );
    }).join("");
  }

  function renderSuspects(suspects) {
    const el = document.getElementById("suspects-grid");
    if (!el) return;
    if (!suspects.length) {
      el.innerHTML = fraudEmptyState("No suspects published yet. Administrators can add suspects in the admin panel.");
      return;
    }
    el.innerHTML = suspects.map(function (s) {
      const photoHtml = s.photo_url
        ? '<img src="' + escapeHtml(s.photo_url) + '" alt="" class="suspect-photo-img">'
        : '<span class="suspect-photo-fallback">' + escapeHtml(s.initials) + "</span>";
      const desc = s.description
        ? '<p class="suspect-detail">' + escapeHtml(s.description) + "</p>"
        : "";
      const trackLink = s.tracking_id
        ? '<a href="track.html?id=' + encodeURIComponent(s.tracking_id) + '" class="fraud-track-link">Case ' + escapeHtml(s.tracking_id) + "</a>"
        : "";
      const risk = s.risk ? riskBadgeHtml(s.risk) : "";
      return (
        '<article class="fraud-card suspect-card"><div class="suspect-photo">' + photoHtml + '</div><h3>' + escapeHtml(s.alias) + "</h3>" + risk +
        '<p class="suspect-detail"><strong>Last seen:</strong> ' + escapeHtml(s.lastSeen) + "</p>" +
        desc + '<span class="case-type-tag">' + escapeHtml(s.caseType) + "</span>" +
        trackLink + "</article>"
      );
    }).join("");
  }

  async function initFraudPage() {
    const alertsEl = document.getElementById("fraud-alerts-grid");
    if (!alertsEl) return;

    const loadingHtml = '<p class="empty-state">Loading data from database…</p>';
    alertsEl.innerHTML = loadingHtml;
    const riskEl = document.getElementById("high-risk-grid");
    const suspectsEl = document.getElementById("suspects-grid");
    if (riskEl) riskEl.innerHTML = loadingHtml;
    if (suspectsEl) suspectsEl.innerHTML = loadingHtml;

    let alerts = [];
    let areas = [];
    let suspects = [];
    let disclaimerText = "Loading data from database…";
    let hasLiveData = false;
    let reportCount = 0;
    let suspectCount = 0;

    try {
      const data = await apiRequest("fraud_public.php");
      if (data.success) {
        alerts = data.alerts || [];
        areas = data.highRiskAreas || [];
        suspects = data.suspects || [];
        if (data.disclaimer) disclaimerText = data.disclaimer;
        hasLiveData = !!data.hasLiveData;
        reportCount = data.reportCount || 0;
        suspectCount = data.suspectCount != null ? data.suspectCount : suspects.length;
      } else {
        showToast(data.message || "Could not load fraud data.", "error");
        disclaimerText = data.message || "Could not load data from database.";
      }
    } catch (err) {
      showToast(err.message || "Could not reach server.", "error");
      disclaimerText = "Could not connect to backend. Start Apache/MySQL in XAMPP.";
      alertsEl.innerHTML = fraudEmptyState(disclaimerText);
      if (riskEl) riskEl.innerHTML = fraudEmptyState("");
      if (suspectsEl) suspectsEl.innerHTML = fraudEmptyState("");
      return;
    }

    renderFraudAlerts(alerts);
    renderHighRiskAreas(areas);
    renderSuspects(suspects);

    const liveBadge = document.getElementById("fraud-live-badge");
    if (liveBadge) liveBadge.classList.toggle("hidden", !hasLiveData);

    const footerNote = document.getElementById("fraud-footer-note");
    if (footerNote) {
      footerNote.textContent = hasLiveData
        ? reportCount + " report" + (reportCount === 1 ? "" : "s") + ", " + suspectCount + " suspect" + (suspectCount === 1 ? "" : "s") + " · All data from database"
        : "No reports or suspects in database yet";
    }

    const suspectsHeading = document.getElementById("suspects-heading");
    if (suspectsHeading) {
      suspectsHeading.textContent = suspectCount > 0
        ? "Suspects (" + suspectCount + ")"
        : "Suspects";
    }

    const alertsHeading = document.getElementById("fraud-alerts-heading");
    if (alertsHeading) {
      alertsHeading.textContent = reportCount > 0
        ? "Reported Cases (" + reportCount + ")"
        : "Reported Cases";
    }

    let disclaimer = document.getElementById("fraud-disclaimer");
    if (!disclaimer) {
      disclaimer = document.createElement("p");
      disclaimer.id = "fraud-disclaimer";
      disclaimer.className = "fraud-disclaimer";
      const body = document.querySelector(".fraud-page-body");
      if (body) body.appendChild(disclaimer);
    }
    disclaimer.textContent = disclaimerText;
  }

  function migrateUsers() {
    const users = getUsers();
    let changed = false;
    users.forEach(function (u) {
      if (!u.fullname && (u.firstname || u.lastname)) {
        u.fullname = ((u.firstname || "") + " " + (u.lastname || "")).trim();
        changed = true;
      }
    });
    if (changed) saveUsers(users);
  }

  function migrateReports() {
    const reports = getReports();
    let changed = false;
    reports.forEach(function (r) {
      if (r.status === "submitted" || r.status === "review") { r.status = "pending"; changed = true; }
      if (!r.updates && r.timeline) {
        r.updates = r.timeline.map(function (t) {
          return { title: t.step, message: "Update recorded.", date: t.date, type: "info" };
        });
        changed = true;
      }
      if (!r.updates) { r.updates = buildDefaultTimeline(); changed = true; }
      if (!r.reporterName) { r.reporterName = "Reporter"; changed = true; }
    });
    if (changed) saveReports(reports);
  }

  function warnIfWrongOrigin() {
    if (window.location.protocol === "file:") {
      showToast("Use XAMPP URL: http://localhost/thefttrack/thefttrack_fn/", "error");
    }
  }

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      document.querySelectorAll(".modal.is-open").forEach(function (m) {
        closeModal(m);
      });
    }
  });

  async function init() {
    moveModalsToBody();
    migrateUsers();
    migrateReports();
    warnIfWrongOrigin();
    await apiLoadSession();
    initNavigation();
    bindClearErrors();

    const page = document.body.getAttribute("data-page");
    if (page === "register") initRegisterPage();
    if (page === "login") initLoginPage();
    if (page === "report") initReportPage();
    if (page === "track") initTrackPage();
    if (page === "updates") initUpdatesPage();
    if (page === "dashboard") initDashboardPage();
    if (page === "fraud") await initFraudPage();
  }

  document.addEventListener("DOMContentLoaded", function () { init(); });
})();
