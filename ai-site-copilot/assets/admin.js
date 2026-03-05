jQuery(function ($) {
  function showBox($box, html, ok) {
    $box.html(html).show();
    $box.css("border-color", ok ? "#bbf7d0" : "#fecaca");
  }

  function escapeHtml(s) {
    return String(s || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  async function api(method, path, body) {
    const url = AISC.restUrl + path;

    const opts = {
      method: method,
      credentials: "include",
      headers: {
        "X-WP-Nonce": AISC.restNonce,
      },
    };

    if (method !== "GET") {
      opts.headers["Content-Type"] = "application/json";
      opts.body = JSON.stringify(body || {});
    }

    const res = await fetch(url, opts);
    const data = await res.json().catch(() => ({}));
    return { ok: res.ok, status: res.status, data: data };
  }

  async function loadPosts() {
    const $selSeo = $("#aisc-post-select");
    const $selIL = $("#aisc-il-post-select");

    if (!$selSeo.length && !$selIL.length) return;

    if ($selSeo.length) $selSeo.html('<option value="">Loading...</option>');
    if ($selIL.length) $selIL.html('<option value="">Loading...</option>');

    const form = new FormData();
    form.append("action", "aisc_get_posts");
    form.append("nonce", AISC.nonce);
    form.append("limit", "30");
    form.append("type", "any");

    try {
      const res = await fetch(AISC.ajaxUrl, {
        method: "POST",
        credentials: "same-origin",
        body: form,
      });

      const json = await res.json().catch(() => ({}));

      if (!json.success) {
        const msg = (json.data && json.data.message) ? json.data.message : "Failed to load posts";
        const html = `<option value="">${escapeHtml(msg)}</option>`;
        if ($selSeo.length) $selSeo.html(html);
        if ($selIL.length) $selIL.html(html);
        return;
      }

      const items = (json.data && json.data.items) ? json.data.items : [];
      if (!items.length) {
        const html = '<option value="">No posts found</option>';
        if ($selSeo.length) $selSeo.html(html);
        if ($selIL.length) $selIL.html(html);
        return;
      }

      const options = items
        .map(function (it) {
          const title = escapeHtml(it.title || "");
          const type = escapeHtml(it.type || "");
          const id = parseInt(it.id, 10) || 0;
          return `<option value="${id}">[${type}] ${title}</option>`;
        })
        .join("");

      const html = `<option value="">Select...</option>${options}`;
      if ($selSeo.length) $selSeo.html(html);
      if ($selIL.length) $selIL.html(html);
    } catch (e) {
      const html = '<option value="">Network error</option>';
      if ($selSeo.length) $selSeo.html(html);
      if ($selIL.length) $selIL.html(html);
    }
  }

  // Quick actions
  $(document).on("click", ".aisc-btn", async function () {
    const action = $(this).data("action");
    const $result = $("#aisc-result");
    if (!action) return;

    showBox($result, "Working...", true);

    if (action === "test_api") {
      const r = await api("POST", "/test", {});
      showBox($result, r.data.message || "Done", r.ok);
    }

    if (action === "scan_site") {
      const r = await api("POST", "/scan", {});
      if (!r.ok) {
        showBox($result, r.data.message || "Scan failed", false);
        return;
      }

      const s = r.data.summary || {};
      const top = r.data.top_issues || [];

      let topHtml = "";
      if (top.length) {
        topHtml =
          `<div style="margin-top:10px;">
            <div class="aisc-muted" style="margin-bottom:6px;">Top problem posts/pages</div>
            <ol style="margin:0 0 0 18px;">` +
          top
            .slice(0, 8)
            .map(function (item) {
              const t = escapeHtml(item.title || "(no title)");
              const type = escapeHtml(item.type || "");
              const wc = parseInt(item.word_count, 10) || 0;

              const issues = (item.issues || [])
                .map(function (x) {
                  return `<span class="aisc-kbd">${escapeHtml(x)}</span>`;
                })
                .join(" ");

              const edit = item.edit_link ? `<a href="${escapeHtml(item.edit_link)}">Edit</a>` : "";

              const qf = item.can_quick_fix
                ? `<button type="button" class="button button-small aisc-quick-fix" data-post-id="${parseInt(item.post_id, 10) || 0}">Quick Fix</button>
                   <span class="aisc-muted aisc-qf-status" data-post-id="${parseInt(item.post_id, 10) || 0}"></span>`
                : `<span class="aisc-muted">Quick Fix disabled</span>`;

              return `<li style="margin-bottom:10px;">
                <div><strong>${t}</strong> <span class="aisc-muted">(${type}, ${wc} words)</span></div>
                <div style="margin-top:4px; display:flex; flex-wrap:wrap; gap:6px;">${issues}</div>
                <div style="margin-top:6px; display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                  ${edit}
                  ${qf}
                </div>
              </li>`;
            })
            .join("") +
          `</ol></div>`;
      }

      showBox(
        $result,
        `✅ Scan Summary:
        <ul style="margin:8px 0 0 18px;">
          <li>Total checked: <strong>${s.total_checked || 0}</strong></li>
          <li>Thin content: <strong>${s.thin_content || 0}</strong></li>
          <li>Missing excerpt: <strong>${s.missing_excerpt || 0}</strong></li>
          <li>Missing featured image: <strong>${s.missing_featured || 0}</strong></li>
          <li>Short title: <strong>${s.short_title || 0}</strong></li>
          <li>No internal links: <strong>${s.no_internal_links || 0}</strong></li>
        </ul>
        ${topHtml}`,
        true
      );
    }
  });

  // ✅ Quick Fix handler
  $(document).on("click", ".aisc-quick-fix", async function () {
    const $btn = $(this);
    const postId = parseInt($btn.data("post-id"), 10);
    if (!postId) return;

    const $status = $('.aisc-qf-status[data-post-id="' + postId + '"]');
    $status.text("Running...");

    $btn.prop("disabled", true);

    const r = await api("POST", "/quick-fix", { post_id: postId });

    if (!r.ok) {
      $status.text((r.data && r.data.message) ? r.data.message : "Failed");
      $btn.prop("disabled", false);
      return;
    }

    const ch = (r.data && r.data.changed) ? r.data.changed : {};
    const parts = [];
    if (ch.seo) parts.push("SEO updated");
    if (ch.excerpt) parts.push("Excerpt saved");
    if (!parts.length) parts.push("No changes");

    $status.text("✅ " + parts.join(" + "));
    $btn.prop("disabled", false);
  });

  // SEO fix
  $(document).on("click", "#aisc-seo-fix-btn", async function () {
    const $out = $("#aisc-seo-result");
    const postId = parseInt($("#aisc-post-select").val(), 10);

    if (!postId) {
      showBox($out, "Please select a post/page first.", false);
      return;
    }

    showBox($out, "Generating SEO... (AI)", true);

    const r = await api("POST", "/seo-fix", { post_id: postId });
    if (!r.ok) {
      showBox($out, r.data.message || "SEO generation failed", false);
      return;
    }

    const focus = (r.data && r.data.focus_keyphrase) ? escapeHtml(r.data.focus_keyphrase) : "";
    const focusHtml = focus
      ? `<div style="margin-top:8px;"><strong>Focus Keyphrase</strong><br>${focus}</div>`
      : "";

    showBox(
      $out,
      `✅ Saved into Yoast SEO:
      <div style="margin-top:8px;"><strong>SEO Title</strong><br>${escapeHtml(r.data.seo_title || "")}</div>
      <div style="margin-top:8px;"><strong>Meta Description</strong><br>${escapeHtml(r.data.meta_description || "")}</div>
      ${focusHtml}
      <div class="aisc-muted" style="margin-top:8px;">Stored in Yoast SEO fields automatically</div>`,
      true
    );
  });

  // Internal Link Engine
  let AISC_IL_SUGGESTIONS = [];

  $(document).on("click", "#aisc-il-suggest-btn", async function () {
    const $out = $("#aisc-il-result");
    const postId = parseInt($("#aisc-il-post-select").val(), 10);

    $("#aisc-il-insert-btn").prop("disabled", true);
    AISC_IL_SUGGESTIONS = [];

    if (!postId) {
      showBox($out, "Please select a post/page first.", false);
      return;
    }

    showBox($out, "Generating internal link suggestions... (AI)", true);

    const r = await api("POST", "/internal-links", { post_id: postId });
    if (!r.ok) {
      showBox($out, r.data.message || "Suggestion failed", false);
      return;
    }

    const items = (r.data && r.data.suggestions) ? r.data.suggestions : [];
    if (!items.length) {
      showBox($out, "No suggestions returned. Try again.", false);
      return;
    }

    AISC_IL_SUGGESTIONS = items;

    const listHtml = items
      .map((it, idx) => {
        const anchor = escapeHtml(it.anchor);
        const url = escapeHtml(it.url);
        const title = escapeHtml(it.title || it.url);
        const reason = escapeHtml(it.reason || "");
        return `
          <div style="padding:10px; border:1px solid #e5e7eb; border-radius:10px; margin-top:10px;">
            <label style="display:flex; gap:10px; align-items:flex-start;">
              <input type="checkbox" class="aisc-il-check" data-idx="${idx}" checked style="margin-top:3px;">
              <div style="flex:1;">
                <div><strong>Anchor:</strong> <code>${anchor}</code></div>
                <div style="margin-top:6px;"><strong>Link:</strong> <a href="${url}" target="_blank" rel="noopener">${title}</a></div>
                ${reason ? `<div class="aisc-muted" style="margin-top:6px;">${reason}</div>` : ""}
              </div>
            </label>
          </div>
        `;
      })
      .join("");

    showBox($out, `✅ Suggestions (select the ones you want to insert): ${listHtml}`, true);
    $("#aisc-il-insert-btn").prop("disabled", false);
  });

  $(document).on("click", "#aisc-il-insert-btn", async function () {
    const $out = $("#aisc-il-result");
    const postId = parseInt($("#aisc-il-post-select").val(), 10);

    if (!postId) {
      showBox($out, "Please select a post/page first.", false);
      return;
    }

    const selectedIdx = $(".aisc-il-check:checked")
      .map(function () {
        return parseInt($(this).data("idx"), 10);
      })
      .get();

    const selected = selectedIdx.map((i) => AISC_IL_SUGGESTIONS[i]).filter(Boolean);

    if (!selected.length) {
      showBox($out, "Please select at least one suggestion.", false);
      return;
    }

    showBox($out, "Inserting links into content...", true);

    const r = await api("POST", "/insert-links", { post_id: postId, suggestions: selected });
    if (!r.ok) {
      showBox($out, r.data.message || "Insert failed", false);
      return;
    }

    showBox(
      $out,
      `✅ Insert complete.
       <div style="margin-top:8px;">
         Links inserted: <strong>${r.data.inserted || 0}</strong><br>
         Skipped: <strong>${r.data.skipped || 0}</strong>
       </div>`,
      true
    );

    $("#aisc-il-insert-btn").prop("disabled", true);
  });

  loadPosts();
});