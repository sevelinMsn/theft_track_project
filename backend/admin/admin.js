(function () {
  "use strict";

  const API = "../";
  const TITLES = {
    overview: "Overview",
    reports: "Theft Reports",
    users: "Registered Users",
    suspects: "Suspects",
    activity: "Activity Log",
  };

  let allSuspects = [];
  let editingSuspectId = null;

  let allReports = [];
  let allUsers = [];
  let statusFilter = "";

  function escapeHtml(s) {
    const d = document.createElement("div");
    d.textContent = s == null ? "" : String(s);
    return d.innerHTML;
  }

  function showMsg(text, ok) {
    const el = document.getElementById("admin-msg");
    if (!el) return;
    el.className = "msg " + (ok ? "ok" : "err");
    el.textContent = text;
    setTimeout(function () {
      if (el.textContent === text) el.textContent = "";
    }, 5000);
  }

  function moveModalToBody(modal) {
    if (modal && modal.parentElement !== document.body) {
      document.body.appendChild(modal);
    }
  }

  function openModal(modal) {
    if (!modal) return;
    moveModalToBody(modal);
    modal.classList.remove("hidden");
    modal.setAttribute("aria-hidden", "false");
    modal.scrollTop = 0;
    requestAnimationFrame(function () {
      modal.classList.add("is-open");
      const body = modal.querySelector(".modal-body");
      if (body) body.scrollTop = 0;
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
    }, 250);
  }

  function initModalClose() {
    const modal = document.getElementById("detail-modal");
    if (!modal) return;
    modal.querySelectorAll("[data-close-modal]").forEach(function (el) {
      el.addEventListener("click", function () {
        closeModal(modal);
      });
    });
  }

  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") return;
    const modal = document.getElementById("detail-modal");
    if (modal && modal.classList.contains("is-open")) closeModal(modal);
  });

  function statusBadge(label) {
    const key = (label || "").toLowerCase();
    let cls = "badge-pending";
    if (key.indexOf("investigation") >= 0) cls = "badge-investigating";
    if (key === "resolved") cls = "badge-resolved";
    return '<span class="badge ' + cls + '">' + escapeHtml(label) + "</span>";
  }

  function switchTab(tab) {
    document.querySelectorAll(".nav-item").forEach(function (btn) {
      btn.classList.toggle("active", btn.getAttribute("data-tab") === tab);
    });
    document.querySelectorAll(".admin-panel").forEach(function (panel) {
      panel.classList.toggle("active", panel.id === "panel-" + tab);
    });
    document.getElementById("page-title").textContent = TITLES[tab] || "Admin";

    if (tab === "overview") loadOverview();
    if (tab === "reports") loadReports(document.getElementById("search-q").value.trim());
    if (tab === "users") loadUsers(document.getElementById("search-users").value.trim());
    if (tab === "suspects") loadSuspects();
    if (tab === "activity") loadActivity();
  }

  async function loadOverview() {
    const statsEl = document.getElementById("overview-stats");
    const recentEl = document.getElementById("overview-recent-reports");
    const usersEl = document.getElementById("overview-recent-users");
    const activityEl = document.getElementById("overview-activity");

    try {
      const res = await fetch(API + "admin_overview.php", { credentials: "same-origin" });
      const data = await res.json();
      if (!data.success) {
        statsEl.innerHTML = '<p class="error-inline">' + escapeHtml(data.message) + "</p>";
        return;
      }

      const s = data.stats || {};
      const r = s.reports || {};

      statsEl.innerHTML =
        statBox(r.total, "Total Reports", "", "reports") +
        statBox(r.pending, "Pending", "warn", "reports") +
        statBox(r.investigating, "Under Investigation", "info", "reports") +
        statBox(r.resolved, "Resolved", "ok", "reports") +
        statBox(s.users, "Registered Users", "users", "users") +
        statBox(s.guest_reports, "Guest Reports", "guest", "reports") +
        statBox(s.registered_reports, "User-Linked Reports", "linked", "reports") +
        statBox(s.reports_this_week || 0, "Reports This Week", "week", "reports") +
        statBox(s.new_users_month || 0, "New Users (30 days)", "newusers", "users");

      const reports = data.recent_reports || [];
      if (!reports.length) {
        recentEl.innerHTML = "<p class='muted'>No reports yet.</p>";
      } else {
        recentEl.innerHTML =
          "<ul class='quick-list'>" +
          reports.map(function (rep) {
            return (
              "<li><strong>" + escapeHtml(rep.tracking_id) + "</strong> — " +
              escapeHtml(rep.itemName) + " " + statusBadge(rep.statusLabel) +
              '<br><small>' + escapeHtml(rep.fullname) + " · " + escapeHtml(rep.created_at) + "</small></li>"
            );
          }).join("") +
          "</ul>";
      }

      if (usersEl) {
        const users = data.recent_users || [];
        if (!users.length) {
          usersEl.innerHTML = "<p class='muted'>No registered users yet.</p>";
        } else {
          usersEl.innerHTML =
            "<ul class='quick-list'>" +
            users.map(function (u) {
              return (
                "<li><strong>" + escapeHtml(u.fullname) + "</strong><br><small>" +
                escapeHtml(u.email) + " · " + escapeHtml(u.phone) + "</small><br>" +
                '<span class="badge badge-info">' + u.report_count + " report(s)</span> " +
                '<button type="button" class="btn btn-ghost btn-sm btn-user-reports-overview" data-id="' + u.id + '">View reports</button></li>'
              );
            }).join("") +
            "</ul>";
          usersEl.querySelectorAll(".btn-user-reports-overview").forEach(function (btn) {
            btn.addEventListener("click", function () {
              openUserReportsModal(parseInt(btn.getAttribute("data-id"), 10));
            });
          });
        }
      }

      const activity = data.recent_activity || [];
      if (!activity.length) {
        activityEl.innerHTML = "<p class='muted'>No activity yet.</p>";
      } else {
        activityEl.innerHTML = activity.map(renderActivityItem).join("");
      }
    } catch (e) {
      statsEl.innerHTML = "<p class='error-inline'>Could not load overview.</p>";
    }
  }

  function statBox(num, label, cls, tab) {
    const tabAttr = tab ? ' data-goto-tab="' + tab + '"' : "";
    return (
      '<button type="button" class="stat-box stat-box--click ' + cls + '"' + tabAttr + ">" +
      '<div class="num">' + (num || 0) + "</div>" +
      '<div class="lbl">' + escapeHtml(label) + "</div></button>"
    );
  }

  function renderActivityItem(a) {
    let msg = "";
    if (a.type === "status") {
      msg = "Status → <strong>" + escapeHtml(a.status) + "</strong>";
    } else {
      msg = escapeHtml(a.note || "");
    }
    return (
      '<div class="activity-item">' +
      "<strong>" + escapeHtml(a.tracking_id) + "</strong> · " + escapeHtml(a.itemName) +
      "<p>" + msg + "</p>" +
      "<small>" + escapeHtml(a.by) + " · " + escapeHtml(a.date) + "</small></div>"
    );
  }

  async function loadReports(query) {
    const wrap = document.getElementById("reports-wrap");
    let url = API + "admin_reports.php";
    if (query) url += "?q=" + encodeURIComponent(query);

    try {
      const res = await fetch(url, { credentials: "same-origin" });
      const data = await res.json();
      if (!data.success) {
        wrap.innerHTML = '<p style="padding:20px;color:#f87171">' + escapeHtml(data.message) + "</p>";
        return;
      }
      allReports = data.reports || [];
      renderReportsTable(filterReportsByStatus(allReports));
    } catch (e) {
      wrap.innerHTML = '<p style="padding:20px;color:#f87171">Could not load reports.</p>';
    }
  }

  function filterReportsByStatus(reports) {
    if (!statusFilter) return reports;
    return reports.filter(function (r) {
      return r.statusLabel === statusFilter;
    });
  }

  function renderReportsTable(reports) {
    const wrap = document.getElementById("reports-wrap");
    if (!reports.length) {
      wrap.innerHTML = "<p style='padding:20px'>No reports found.</p>";
      return;
    }

    let html =
      "<table><thead><tr>" +
      "<th>Tracking ID</th><th>Reporter</th><th>Item</th><th>Location</th><th>Status</th><th>Account</th><th>Actions</th>" +
      "</tr></thead><tbody>";

    reports.forEach(function (r) {
      const user = r.linked_user;
      const userHtml = user
        ? '<span class="user-tag">Registered</span><br>' + escapeHtml(user.fullname) + "<br>" + escapeHtml(user.email)
        : '<span class="user-tag">Guest</span>';

      html += "<tr>";
      html += "<td><strong>" + escapeHtml(r.tracking_id) + "</strong><br><small>" + escapeHtml(r.created_at) + "</small></td>";
      html += "<td>" + escapeHtml(r.fullname) + "<br>" + escapeHtml(r.phone) + (r.email ? "<br>" + escapeHtml(r.email) : "") + "</td>";
      html += "<td>" + escapeHtml(r.itemName) + "</td>";
      html += "<td>" + escapeHtml(r.location) + "</td>";
      html += "<td>" + statusBadge(r.statusLabel) + "</td>";
      html += "<td>" + userHtml + "</td>";
      html += '<td class="actions-cell"><form class="inline-form status-form" data-id="' + escapeHtml(r.tracking_id) + '">';
      html += '<select name="status">';
      ["Pending", "Under Investigation", "Resolved"].forEach(function (s) {
        html += '<option value="' + s + '"' + (r.statusLabel === s ? " selected" : "") + ">" + s + "</option>";
      });
      html += '</select><textarea name="note" placeholder="Investigation note (optional)" rows="2"></textarea>';
      html += '<div class="action-btns">';
      html += '<button type="submit" class="btn btn-primary btn-sm">Update</button>';
      html += '<button type="button" class="btn btn-ghost btn-sm btn-view" data-id="' + escapeHtml(r.tracking_id) + '">Details</button>';
      html += '<button type="button" class="btn btn-danger btn-sm btn-delete" data-id="' + escapeHtml(r.tracking_id) + '">Delete</button>';
      html += "</div></form></td></tr>";
    });

    html += "</tbody></table>";
    wrap.innerHTML = html;
    bindReportActions(wrap);
  }

  function bindReportActions(wrap) {
    wrap.querySelectorAll(".status-form").forEach(function (form) {
      form.addEventListener("submit", async function (e) {
        e.preventDefault();
        const id = form.getAttribute("data-id");
        const res = await fetch(API + "admin_update_status.php", {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            tracking_id: id,
            status: form.querySelector('[name="status"]').value,
            note: form.querySelector('[name="note"]').value.trim(),
          }),
        });
        const data = await res.json();
        showMsg(data.message, data.success);
        if (data.success) {
          form.querySelector('[name="note"]').value = "";
          loadReports(document.getElementById("search-q").value.trim());
        }
      });
    });

    wrap.querySelectorAll(".btn-view").forEach(function (btn) {
      btn.addEventListener("click", function () {
        const report = allReports.find(function (r) {
          return r.tracking_id === btn.getAttribute("data-id");
        });
        if (report) openReportModal(report);
      });
    });

    wrap.querySelectorAll(".btn-delete").forEach(function (btn) {
      btn.addEventListener("click", async function () {
        const id = btn.getAttribute("data-id");
        if (!confirm("Delete report " + id + "? This cannot be undone.")) return;
        const res = await fetch(API + "admin_delete_report.php", {
          method: "POST",
          credentials: "same-origin",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ tracking_id: id }),
        });
        const data = await res.json();
        showMsg(data.message, data.success);
        if (data.success) loadReports(document.getElementById("search-q").value.trim());
      });
    });
  }

  async function loadUsers(query) {
    const wrap = document.getElementById("users-wrap");
    let url = API + "admin_users.php";
    if (query) url += "?q=" + encodeURIComponent(query);

    try {
      const res = await fetch(url, { credentials: "same-origin" });
      const data = await res.json();
      if (!data.success) {
        wrap.innerHTML = '<p style="padding:20px;color:#f87171">' + escapeHtml(data.message) + "</p>";
        return;
      }
      allUsers = data.users || [];
      const countEl = document.getElementById("users-count");
      if (countEl) {
        countEl.textContent = allUsers.length + " user(s) shown";
      }
      renderUsersTable(allUsers);
    } catch (e) {
      wrap.innerHTML = '<p style="padding:20px;color:#f87171">Could not load users.</p>';
    }
  }

  function renderUsersTable(users) {
    const wrap = document.getElementById("users-wrap");
    if (!users.length) {
      wrap.innerHTML = "<p style='padding:20px'>No registered users found.</p>";
      return;
    }

    let html =
      "<table><thead><tr>" +
      "<th>ID</th><th>Full Name</th><th>Email</th><th>Phone</th><th>Joined</th><th>Reports</th><th>Actions</th>" +
      "</tr></thead><tbody>";

    users.forEach(function (u) {
      html += "<tr>";
      html += "<td>#" + u.id + "</td>";
      html += "<td><strong>" + escapeHtml(u.fullname) + "</strong></td>";
      html += "<td>" + escapeHtml(u.email) + "</td>";
      html += "<td>" + escapeHtml(u.phone) + "</td>";
      html += "<td><small>" + escapeHtml(u.created_at) + "</small></td>";
      html += '<td><span class="badge badge-info">' + u.report_count + " report(s)</span></td>";
      html += '<td class="actions-cell">';
      html += '<button type="button" class="btn btn-primary btn-sm btn-user-reports" data-id="' + u.id + '">View Reports</button>';
      html += "</td></tr>";
    });

    html += "</tbody></table>";
    wrap.innerHTML = html;

    wrap.querySelectorAll(".btn-user-reports").forEach(function (btn) {
      btn.addEventListener("click", function () {
        openUserReportsModal(parseInt(btn.getAttribute("data-id"), 10));
      });
    });
  }

  async function openUserReportsModal(userId) {
    const modal = document.getElementById("detail-modal");
    const body = document.getElementById("modal-body");
    document.getElementById("modal-title").textContent = "User Reports";
    body.innerHTML = "<p>Loading…</p>";
    openModal(modal);

    try {
      const res = await fetch(API + "admin_user_reports.php?user_id=" + userId, { credentials: "same-origin" });
      const data = await res.json();
      if (!data.success) {
        body.innerHTML = "<p>" + escapeHtml(data.message) + "</p>";
        return;
      }
      const u = data.user;
      let html =
        '<div class="detail-grid">' +
        detailRow("Name", u.fullname) +
        detailRow("Email", u.email) +
        detailRow("Phone", u.phone) +
        detailRow("Member since", u.created_at) +
        "</div><h3 class='modal-subheading'>Reports (" + data.reports.length + ")</h3>";

      if (!data.reports.length) {
        html += "<p class='muted'>No reports filed by this user.</p>";
      } else {
        html += "<ul class='quick-list'>";
        data.reports.forEach(function (r) {
          html +=
            "<li><strong>" + escapeHtml(r.tracking_id) + "</strong> — " +
            escapeHtml(r.itemName) + " " + statusBadge(r.statusLabel) +
            ' <button type="button" class="btn btn-ghost btn-sm btn-view-inline" data-id="' +
            escapeHtml(r.tracking_id) + '">Open</button></li>';
        });
        html += "</ul>";
      }
      body.innerHTML = html;

      body.querySelectorAll(".btn-view-inline").forEach(function (b) {
        b.addEventListener("click", function () {
          const rep = data.reports.find(function (r) {
            return r.tracking_id === b.getAttribute("data-id");
          });
          if (rep) openReportModal(rep);
        });
      });
    } catch (e) {
      body.innerHTML = "<p>Could not load user reports.</p>";
    }
  }

  async function loadActivity() {
    const el = document.getElementById("activity-full-list");
    el.innerHTML = "<p>Loading…</p>";
    try {
      const res = await fetch(API + "admin_overview.php", { credentials: "same-origin" });
      const data = await res.json();
      const activity = data.recent_activity || [];
      if (!activity.length) {
        el.innerHTML = "<p class='muted'>No activity recorded yet. Updates appear when you change case status or add notes.</p>";
        return;
      }
      el.innerHTML = activity.map(renderActivityItem).join("");
    } catch (e) {
      el.innerHTML = "<p class='error-inline'>Could not load activity.</p>";
    }
  }

  function openReportModal(r) {
    const modal = document.getElementById("detail-modal");
    const body = document.getElementById("modal-body");
    document.getElementById("modal-title").textContent = r.tracking_id + " — " + r.itemName;

    let notesHtml = "";
    if (r.notes && r.notes.length) {
      notesHtml = '<div class="notes-list"><strong>Investigation Notes</strong>';
      r.notes.forEach(function (n) {
        notesHtml += '<div class="note-item"><p>' + escapeHtml(n.text) + "</p><small>" + escapeHtml(n.by) + " · " + escapeHtml(n.date) + "</small></div>";
      });
      notesHtml += "</div>";
    }

    let userHtml = "";
    if (r.linked_user) {
      userHtml =
        '<div class="notes-list"><strong>Linked Account</strong><p>' +
        escapeHtml(r.linked_user.fullname) + " · " + escapeHtml(r.linked_user.email) + " · " + escapeHtml(r.linked_user.phone) + "</p></div>";
    }

    body.innerHTML =
      '<div class="detail-grid">' +
      detailRow("Tracking ID", r.tracking_id) +
      detailRow("Status", r.statusLabel) +
      detailRow("Reporter", r.fullname) +
      detailRow("Phone", r.phone) +
      detailRow("Email", r.email || "—") +
      detailRow("Item", r.itemName) +
      detailRow("Category", r.category || "—") +
      detailRow("Location", r.location) +
      (r.incidentDate ? detailRow("Date of theft", r.incidentDate) : "") +
      detailRow("Filed", r.created_at) +
      detailRow("Description", r.description, true) +
      (r.suspect ? detailRow("Suspect info", r.suspect, true) : "") +
      "</div>" +
      userHtml +
      notesHtml +
      '<form id="modal-note-form" style="margin-top:16px">' +
      '<label>Add investigation note</label>' +
      '<textarea name="note" required placeholder="Enter note…"></textarea>' +
      '<button type="submit" class="btn btn-primary btn-sm">Add Note</button></form>';

    openModal(modal);

    document.getElementById("modal-note-form").onsubmit = async function (e) {
      e.preventDefault();
      const note = e.target.querySelector('[name="note"]').value.trim();
      const res = await fetch(API + "admin_add_note.php", {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ tracking_id: r.tracking_id, note: note }),
      });
      const data = await res.json();
      showMsg(data.message, data.success);
      if (data.success) {
        loadReports(document.getElementById("search-q").value.trim());
        closeModal(modal);
      }
    };
  }

  function detailRow(label, value, fullWidth) {
    const cls = fullWidth ? "detail-row detail-row--full" : "detail-row";
    return (
      '<div class="' + cls + '"><span>' + escapeHtml(label) + "</span><span>" + escapeHtml(value) + "</span></div>"
    );
  }

  function resetSuspectForm() {
    editingSuspectId = null;
    const form = document.getElementById("suspect-form");
    if (!form) return;
    form.reset();
    document.getElementById("suspect-id").value = "";
    document.getElementById("suspect-risk").value = "medium";
    document.getElementById("suspect-status").value = "active";
    document.getElementById("suspect-form-title").textContent = "Add suspect";
    document.getElementById("btn-save-suspect").textContent = "Save suspect";
    document.getElementById("btn-cancel-suspect").classList.add("hidden");
    const preview = document.getElementById("suspect-photo-preview");
    if (preview) {
      preview.classList.add("hidden");
      preview.innerHTML = "";
    }
  }

  function fillSuspectForm(s) {
    editingSuspectId = s.id;
    document.getElementById("suspect-id").value = String(s.id);
    document.getElementById("suspect-alias").value = s.alias || "";
    document.getElementById("suspect-case-type").value = s.caseType || "";
    document.getElementById("suspect-last-seen").value = s.lastSeen || "";
    document.getElementById("suspect-description").value = s.description || "";
    document.getElementById("suspect-risk").value = s.risk || "medium";
    document.getElementById("suspect-status").value = s.status || "active";
    document.getElementById("suspect-tracking").value = s.tracking_id || "";
    document.getElementById("suspect-photo").value = "";
    document.getElementById("suspect-form-title").textContent = "Edit suspect";
    document.getElementById("btn-save-suspect").textContent = "Update suspect";
    document.getElementById("btn-cancel-suspect").classList.remove("hidden");
    const preview = document.getElementById("suspect-photo-preview");
    if (preview && s.photo_admin_url) {
      preview.classList.remove("hidden");
      preview.innerHTML = '<img src="' + escapeHtml(s.photo_admin_url) + '" alt="Current photo">';
    } else if (preview) {
      preview.classList.add("hidden");
      preview.innerHTML = "";
    }
  }

  async function loadSuspects() {
    const wrap = document.getElementById("suspects-wrap");
    const countEl = document.getElementById("suspects-count");
    if (!wrap) return;
    wrap.innerHTML = '<p style="padding:20px">Loading suspects…</p>';

    try {
      const res = await fetch(API + "admin_suspects.php", { credentials: "same-origin" });
      const data = await res.json();
      if (!data.success) {
        wrap.innerHTML = '<p class="error-inline">' + escapeHtml(data.message) + "</p>";
        return;
      }
      allSuspects = data.suspects || [];
      if (countEl) {
        countEl.textContent = allSuspects.length + " total · " +
          allSuspects.filter(function (s) { return s.status === "active"; }).length + " active";
      }
      renderSuspectsAdmin();
    } catch (err) {
      wrap.innerHTML = '<p class="error-inline">Could not load suspects.</p>';
    }
  }

  function renderSuspectsAdmin() {
    const wrap = document.getElementById("suspects-wrap");
    if (!wrap) return;
    if (!allSuspects.length) {
      wrap.innerHTML = '<p class="empty-inline">No suspects yet. Use the form to add one.</p>';
      return;
    }

    wrap.innerHTML =
      '<div class="suspects-admin-grid">' +
      allSuspects.map(function (s) {
        const photo = s.photo_admin_url
          ? '<img src="' + escapeHtml(s.photo_admin_url) + '" alt="" class="suspect-admin-thumb">'
          : '<span class="suspect-admin-thumb suspect-admin-thumb--empty">' + escapeHtml(s.initials) + "</span>";
        const statusCls = s.status === "active" ? "badge-resolved" : "badge-pending";
        return (
          '<article class="suspect-admin-card">' + photo +
          '<div class="suspect-admin-body"><h3>' + escapeHtml(s.alias) + "</h3>" +
          '<p><strong>Last seen:</strong> ' + escapeHtml(s.lastSeen) + "</p>" +
          '<p><span class="case-type-tag">' + escapeHtml(s.caseType) + "</span> " +
          '<span class="badge ' + statusCls + '">' + escapeHtml(s.status) + "</span></p>" +
          (s.tracking_id ? '<p><code>' + escapeHtml(s.tracking_id) + "</code></p>" : "") +
          '<div class="suspect-admin-actions">' +
          '<button type="button" class="btn btn-ghost btn-sm" data-edit-suspect="' + s.id + '">Edit</button>' +
          '<button type="button" class="btn btn-ghost btn-sm btn-danger-text" data-delete-suspect="' + s.id + '">Delete</button>' +
          "</div></div></article>"
        );
      }).join("") +
      "</div>";

    wrap.querySelectorAll("[data-edit-suspect]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        const id = parseInt(btn.getAttribute("data-edit-suspect"), 10);
        const s = allSuspects.find(function (x) { return x.id === id; });
        if (s) fillSuspectForm(s);
      });
    });

    wrap.querySelectorAll("[data-delete-suspect]").forEach(function (btn) {
      btn.addEventListener("click", async function () {
        const id = parseInt(btn.getAttribute("data-delete-suspect"), 10);
        if (!confirm("Delete this suspect?")) return;
        try {
          const res = await fetch(API + "admin_suspects.php", {
            method: "DELETE",
            credentials: "same-origin",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id: id }),
          });
          const data = await res.json();
          showMsg(data.message, data.success);
          if (data.success) {
            if (editingSuspectId === id) resetSuspectForm();
            loadSuspects();
          }
        } catch (err) {
          showMsg("Delete failed.", false);
        }
      });
    });
  }

  function initSuspectForm() {
    const form = document.getElementById("suspect-form");
    const cancelBtn = document.getElementById("btn-cancel-suspect");
    const photoInput = document.getElementById("suspect-photo");
    if (!form) return;

    if (cancelBtn) {
      cancelBtn.addEventListener("click", resetSuspectForm);
    }

    if (photoInput) {
      photoInput.addEventListener("change", function () {
        const file = photoInput.files[0];
        const preview = document.getElementById("suspect-photo-preview");
        if (!preview) return;
        if (!file) {
          preview.classList.add("hidden");
          preview.innerHTML = "";
          return;
        }
        if (file.size > 2 * 1024 * 1024) {
          showMsg("Photo must be 2MB or smaller.", false);
          photoInput.value = "";
          return;
        }
        const reader = new FileReader();
        reader.onload = function (ev) {
          preview.classList.remove("hidden");
          preview.innerHTML = '<img src="' + ev.target.result + '" alt="Preview">';
        };
        reader.readAsDataURL(file);
      });
    }

    form.addEventListener("submit", async function (e) {
      e.preventDefault();
      const fd = new FormData(form);
      if (editingSuspectId) {
        fd.set("id", String(editingSuspectId));
      }
      try {
        const res = await fetch(API + "admin_suspects.php", {
          method: "POST",
          credentials: "same-origin",
          body: fd,
        });
        const data = await res.json();
        showMsg(data.message, data.success);
        if (data.success) {
          resetSuspectForm();
          loadSuspects();
        }
      } catch (err) {
        showMsg("Could not save suspect.", false);
      }
    });
  }


  document.addEventListener("click", function (e) {
    const goto = e.target.closest("[data-goto-tab]");
    if (goto) { e.preventDefault(); switchTab(goto.getAttribute("data-goto-tab")); }
  });

  document.querySelectorAll(".nav-item").forEach(function (btn) {
    btn.addEventListener("click", function () {
      switchTab(btn.getAttribute("data-tab"));
    });
  });

  document.getElementById("btn-search").addEventListener("click", function () {
    loadReports(document.getElementById("search-q").value.trim());
  });

  document.getElementById("btn-clear").addEventListener("click", function () {
    document.getElementById("search-q").value = "";
    statusFilter = "";
    document.getElementById("filter-status").value = "";
    loadReports("");
  });

  document.getElementById("search-q").addEventListener("keydown", function (e) {
    if (e.key === "Enter") loadReports(e.target.value.trim());
  });

  document.getElementById("filter-status").addEventListener("change", function (e) {
    statusFilter = e.target.value;
    renderReportsTable(filterReportsByStatus(allReports));
  });

  document.getElementById("btn-search-users").addEventListener("click", function () {
    loadUsers(document.getElementById("search-users").value.trim());
  });

  document.getElementById("btn-clear-users").addEventListener("click", function () {
    document.getElementById("search-users").value = "";
    loadUsers("");
  });

  document.getElementById("search-users").addEventListener("keydown", function (e) {
    if (e.key === "Enter") loadUsers(e.target.value.trim());
  });

  moveModalToBody(document.getElementById("detail-modal"));
  initModalClose();
  initSuspectForm();
  loadOverview();
})();
