# Recipe SEO AI Pro — Manual Regression Testing Checklist

| Field | Value |
|-------|-------|
| **Document** | QA Phase — Manual Regression Checklist |
| **Plugin** | Recipe SEO AI Pro 1.0.0 |
| **Architecture baseline** | Phases 1, 2A, 2B, 2C (skeleton, settings, repositories, AI HTTP providers) |
| **Schema version** | `rsaip_db_version` = `1.0.2` |
| **Option key** | `rsaip_settings` |
| **Scope** | Manual QA only — no code changes, no new features, no refactors |
| **Tester** | ________________ |
| **Environment** | ________________ (WP version / PHP / DB / theme) |
| **Date** | ________________ |
| **Build / commit** | ________________ |

---

## How to use this document

1. Run tests in the order of the **Recommended run order** when possible.
2. For each case, mark **Pass / Fail / Blocked / N/A**.
3. On Fail: record steps to reproduce, screenshots, browser console / network errors, and PHP / debug.log excerpts.
4. Do not start Phase 2D until Critical + High sections are green (or waived with documented reason).

### Pass criteria legend

| Mark | Meaning |
|------|---------|
| **Pass** | Matches Expected result |
| **Fail** | Deviates from Expected result |
| **Blocked** | Cannot run (missing credentials, env, data) |
| **N/A** | Not applicable to this environment |

### Recommended run order

1. Activation → Settings → Link Graph  
2. Internal Linking → Auto Linking → Orphans / Audit  
3. Image SEO → Recipe → Public Ratings → Schema  
4. GSC → Performance → AI → Bulk → Cron  
5. Reports / Exports  
6. Repository + AI Provider smoke checks  
7. Deactivation → Reactivation → Upgrade path → Uninstall (destructive last)

### Quick DB / cron helpers (wp-cli optional)

```bash
wp option get rsaip_settings --format=json
wp option get rsaip_db_version
wp db query "SHOW TABLES LIKE '%rsaip_%'"
wp db query "SHOW INDEX FROM wp_rsaip_link_graph"
wp cron event list | findstr rsaip
```

Replace `wp_` with your table prefix.

---

## Environment matrix (fill before testing)

| Item | Value | Notes |
|------|-------|-------|
| WordPress | | |
| PHP | | |
| MySQL / MariaDB | | |
| Multisite | Yes / No | |
| Composer `vendor/` present | Yes / No | Fallback autoloader must work if No |
| AI endpoint / model | | OpenAI-compatible |
| GSC property | | Optional |
| PSI API key | | Optional |
| ZipArchive available | Yes / No | Affects XLSX |
| Sample content | ≥10 posts with internal links; ≥1 recipe card post; ≥1 post with images | |

---

# 1. Activation

### TC-ACT-01 — Fresh activation (clean site)

| | |
|---|---|
| **Preconditions** | Plugin not installed. No `rsaip_*` tables/options. Admin capability. |
| **Test steps** | 1. Upload / copy plugin. 2. Activate **Recipe SEO AI Pro**. 3. Open admin menu. 4. Check DB tables and options. 5. Check scheduled cron events. |
| **Expected result** | No PHP fatals. Menu **Recipe SEO AI Pro** with subpages present. Tables exist: `{prefix}rsaip_link_graph`, `rsaip_post_metrics`, `rsaip_link_suggestions`, `rsaip_bulk_queue`, `rsaip_recipe_votes`. Option `rsaip_settings` created with defaults. `rsaip_db_version` = `1.0.2`. Crons scheduled: `rsaip_cron_rebuild_link_graph` (hourly), `rsaip_cron_check_broken_links` (twicedaily), `rsaip_cron_process_bulk_queue` (hourly). |
| **Edge cases** | Activate with no Composer `vendor/` directory — must still boot via SPL autoloader. Activate as non-English site locale — menus load without fatals. |
| **Failure cases** | Fatal on activate; missing tables; missing menu; crons not scheduled; white screen on `plugins_loaded`. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-ACT-02 — Reactivate after deactivate (data preserved)

| | |
|---|---|
| **Preconditions** | Plugin previously activated with custom settings and graph data. |
| **Test steps** | 1. Note settings + a link-graph row count. 2. Deactivate. 3. Reactivate. 4. Compare settings and table data. |
| **Expected result** | Settings and table rows preserved. Crons rescheduled if cleared. Admin UI works. |
| **Edge cases** | Reactivate immediately after deactivate. |
| **Failure cases** | Settings reset to empty; tables dropped on deactivate; fatals on reactivate. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

---

# 2. Upgrade

### TC-UPG-01 — Upgrade to schema 1.0.2 (external links + votes)

| | |
|---|---|
| **Preconditions** | Staging DB from older install with `{prefix}rsaip_link_graph` still having UNIQUE index `uniq_internal`, and **no** `rsaip_recipe_votes` table. Set `rsaip_db_version` to older value (e.g. `1.0.0` or empty). Deploy current plugin code. |
| **Test steps** | 1. Load any admin page (triggers `maybe_upgrade`). 2. `SHOW INDEX` on link_graph. 3. Confirm votes table exists. 4. Read `rsaip_db_version`. 5. Rebuild link graph; confirm multiple external links (`to_post_id = 0`) can exist per source post. |
| **Expected result** | Index `uniq_internal` gone. Index `from_to` present (non-unique). Votes table created. `rsaip_db_version` = `1.0.2`. Existing graph rows preserved. Multiple external outs per post allowed after rebuild. |
| **Edge cases** | Upgrade when link_graph table missing (fresh install path). Upgrade when votes table already exists. Re-run upgrade when already on 1.0.2 (no-op). |
| **Failure cases** | Version never bumps (stuck if `uniq_internal` cannot drop). Data loss on ALTER. Duplicate key errors on external links after “upgrade”. Votes table missing → public ratings 503. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-UPG-02 — Plugin file update without deactivate

| | |
|---|---|
| **Preconditions** | Active plugin; replace files with current build (Phases 1–2C). |
| **Test steps** | 1. Replace plugin files. 2. Reload Dashboard. 3. Spot-check Settings, AI page, one AJAX action. |
| **Expected result** | No fatals. Facades resolve (settings/repos/AI provider). Existing settings unchanged. |
| **Edge cases** | Opcache stale — hard refresh / restart PHP if class not found. |
| **Failure cases** | Class not found (`RecipeSeoAiPro\…`); helpers before autoload; settings wiped. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

---

# 3. Deactivation

### TC-DEA-01 — Deactivate clears crons, keeps data

| | |
|---|---|
| **Preconditions** | Plugin active; crons scheduled; settings + tables populated. |
| **Test steps** | 1. Deactivate plugin. 2. List cron events for `rsaip_*`. 3. Check options/tables still exist. 4. Confirm admin menu gone. |
| **Expected result** | Three `rsaip_cron_*` hooks cleared. `rsaip_settings`, `rsaip_db_version`, and all five tables **still exist**. Front-end recipe rating assets not enqueued. |
| **Edge cases** | Deactivate while bulk queue has pending jobs — data remains for later reactivate. |
| **Failure cases** | Tables/options deleted on deactivate; cron hooks remain; fatals during deactivate. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

---

# 4. Uninstall

> **Destructive.** Use a disposable site/DB snapshot. Last test in the suite.

### TC-UNI-01 — Full uninstall cleanup

| | |
|---|---|
| **Preconditions** | Plugin installed with data, secrets, GSC token transient, dashboard stats transient. Snapshot DB first. |
| **Test steps** | 1. Delete plugin via WP admin (triggers `uninstall.php`). 2. Verify tables dropped. 3. Verify options/transients deleted. 4. Verify crons cleared. |
| **Expected result** | Dropped: all five `rsaip_*` tables. Deleted options: `rsaip_settings`, `rsaip_db_version`. Deleted transients: `rsaip_dashboard_stats`, `rsaip_gsc_token`, `rsaip_gsc_indexed_posts`. Crons cleared. No orphaned plugin menu. |
| **Edge cases** | Uninstall on multisite (per-site tables/options as applicable). Uninstall with empty tables. |
| **Failure cases** | Tables left behind; secrets left in options; uninstall fatals; site broken after delete. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

---

# 5. Settings

### TC-SET-01 — Load Settings page

| | |
|---|---|
| **Preconditions** | Plugin active; admin user with `manage_options` (or configured capability). |
| **Test steps** | Open **Settings** (`rsaip-settings`). |
| **Expected result** | Page loads. Defaults visible. Secret fields (`ai_api_key`, `psi_api_key`, `gsc_private_key_pem`) show empty inputs with placeholder `••••••••` when a secret is saved — never the raw secret in HTML source. |
| **Edge cases** | View page source / DevTools Elements — raw PEM/API keys must not appear. |
| **Failure cases** | Secrets echoed in HTML; PHP notices; blank page. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-SET-02 — Partial save does not wipe sibling keys (Fix 1)

| | |
|---|---|
| **Preconditions** | Configure AI endpoint/key/model **and** GSC site URL **and** PSI key (or distinct values in multiple sections). |
| **Test steps** | 1. Save only Auto Linking form (or only GSC form, or only Performance form). 2. Reload Settings / check option. |
| **Expected result** | Unposted sibling keys remain unchanged (AI settings survive GSC-only save, etc.). |
| **Edge cases** | Save Auto Linking with checkbox unchecked — `auto_insert_internal_links` becomes `0` only when that form is submitted. Inline setting updates via AJAX for non-secrets. |
| **Failure cases** | Other sections reset to defaults/empty; secrets wiped; auto-link unexpectedly toggled by unrelated form. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-SET-03 — Secret keep-on-empty / placeholder (Fix 5)

| | |
|---|---|
| **Preconditions** | Valid `ai_api_key`, `psi_api_key`, and/or `gsc_private_key_pem` already saved. |
| **Test steps** | 1. Open Settings. 2. Leave secret fields blank (or submit placeholder). 3. Save. 4. Verify features that need those secrets still work (or inspect option via wp-cli — not HTML). |
| **Expected result** | Stored secrets unchanged. Note text indicates a key is already saved. |
| **Edge cases** | Submit literal `••••••••` — treated as unchanged. Paste a new key — replaces old. |
| **Failure cases** | Secrets cleared to empty; features break after “blank save”. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-SET-04 — Inline AJAX cannot update secrets

| | |
|---|---|
| **Preconditions** | Admin UI with inline setting controls; browser DevTools Network. |
| **Test steps** | Attempt to update a secret key via `rsaip_update_settings_inline` (or any UI that posts a secret key). |
| **Expected result** | Request rejected (`403`, message that secrets cannot be updated inline). Stored secret unchanged. |
| **Edge cases** | Non-secret inline keys (e.g. thresholds) still update successfully. |
| **Failure cases** | Secret updated via inline AJAX; unknown key accepted; capability bypass. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-SET-05 — Capability / nonce on settings AJAX

| | |
|---|---|
| **Preconditions** | Editor or subscriber account (no manage capability). |
| **Test steps** | Call an admin AJAX action (e.g. `rsaip_get_dashboard_stats`) as low-privilege user. |
| **Expected result** | `403` Access denied. Invalid/missing nonce rejected. |
| **Edge cases** | Logged-out user hits admin AJAX — denied. |
| **Failure cases** | Low-privilege user can change settings or run tools. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

---

# 6. AI

### TC-AI-01 — Provider configured: generate titles

| | |
|---|---|
| **Preconditions** | Settings: `ai_provider` = `openai_compatible`; valid `ai_endpoint`, `ai_api_key`, `ai_model`; published post ID known. |
| **Test steps** | AI Recommendations page → enter post ID → **Generate Titles**. Optionally **Apply Title**. |
| **Expected result** | Up to 5 title ideas (≤60 chars). Apply updates post title. No PHP fatals. Network shows Chat Completions-style request to configured endpoint. |
| **Edge cases** | Very short / thin post content. Non-ASCII titles. |
| **Failure cases** | Empty response with valid API; wrong model body; secrets leaked in UI; title not applied. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-AI-02 — Meta description, keywords, FAQ, recommendations, fix post

| | |
|---|---|
| **Preconditions** | Same as TC-AI-01. |
| **Test steps** | For same post: Analyze Post; Generate Recommendations; Generate Meta Description (+ Apply); Generate Keywords (+ Apply to chosen target); Generate FAQ; Fix With AI (review before trusting content). |
| **Expected result** | Each action returns usable content or clear JSON success payload. Apply actions persist meta/title/keywords as designed. Heuristics must **not** replace AI when provider is correctly configured and API succeeds. |
| **Edge cases** | Apply empty title/meta — should not wipe with garbage. FAQ HTML safe. |
| **Failure cases** | Silent no-op; wrong post updated; content corrupted; unescaped HTML XSS in admin output. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-AI-03 — Article generation + draft

| | |
|---|---|
| **Preconditions** | AI configured; topic/keywords per UI. |
| **Test steps** | Generate article → Create article draft. |
| **Expected result** | Draft post created with generated content. Editable in WP editor. |
| **Edge cases** | Long prompts / timeout near `ai_timeout_seconds` limit. |
| **Failure cases** | Draft not created; partial HTML break; fatal during create. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-AI-04 — Disabled / missing key → heuristics (no successful remote AI)

| | |
|---|---|
| **Preconditions** | Set `ai_provider` to `disabled` **or** clear API key (via proper secret replace), keep other settings. |
| **Test steps** | Generate titles / meta / FAQ for a post. |
| **Expected result** | Features still return heuristic/fallback content (not a white screen). No successful remote completion when `use_ai()` is false. |
| **Edge cases** | Provider disabled but leftover key still in DB — UI paths that check `use_ai()` should not call AI. |
| **Failure cases** | Fatal; empty hard failure with no fallback; unexpected live API call when provider disabled (via normal UI). |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-AI-05 — Bad endpoint / unsafe URL / HTTP error

| | |
|---|---|
| **Preconditions** | Ability to temporarily set invalid endpoint (e.g. `http://127.0.0.1`, non-HTTPS private IP, or 401-returning URL). |
| **Test steps** | Trigger Generate Titles. |
| **Expected result** | Graceful error / fallback. SSRF-safe rejection for unsafe URLs (`Invalid AI endpoint` / config error). No PHP fatal. |
| **Edge cases** | Timeout equal to `ai_timeout_seconds`. Malformed JSON from provider. |
| **Failure cases** | Site hang; uncaught exception; secret key logged in cleartext in UI. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

---

# 7. Internal Linking

### TC-IL-01 — Generate and list suggestions

| | |
|---|---|
| **Preconditions** | ≥5–10 published posts with topical overlap; link graph rebuilt recently preferred. |
| **Test steps** | Internal Linking → set suggestion limit → **Generate Suggestions** → table loads via `rsaip_list_suggestions`. |
| **Expected result** | Rows with from/to posts, anchors, scores. Respects `link_suggestion_limit` / `min_relevance_score` settings. |
| **Edge cases** | Limit = 1 and limit = 20. Site with almost no linkable posts → empty but successful response. |
| **Failure cases** | SQL/PHP errors; empty when obvious candidates exist; duplicate spam suggestions. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-IL-02 — Insert links for a post

| | |
|---|---|
| **Preconditions** | Suggestions exist for a target post. |
| **Test steps** | Insert links for that post (UI control / AJAX `rsaip_insert_links_for_post`). Open post content. |
| **Expected result** | Internal links inserted within `max_links_per_post`. Content remains valid HTML. Post updated intentionally. |
| **Edge cases** | Re-run insert — should not explode link count beyond max. Posts already dense with links. |
| **Failure cases** | Broken HTML; wrong target URLs; links to drafts/trash; content wiped. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

---

# 8. Auto Linking

### TC-AL-01 — Settings + batch run

| | |
|---|---|
| **Preconditions** | Suggestions available; Auto Linking page accessible. |
| **Test steps** | Configure min score, max links, post types, statuses. Save. Run **Run Auto Linking Batch Now**. |
| **Expected result** | Batch processes eligible posts; links inserted per rules. Settings persist (partial-save safe). |
| **Edge cases** | Only `post` type selected; status `publish` only — drafts untouched. |
| **Failure cases** | Batch updates wrong post types; settings wipe AI/GSC keys; fatals mid-batch. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-AL-02 — Auto-insert on save_post

| | |
|---|---|
| **Preconditions** | Enable `auto_insert_internal_links`; matching post type/status; suggestions exist. |
| **Test steps** | Edit and update a published post. Inspect content for new internal links. |
| **Expected result** | Links auto-inserted on save per rules. Disable setting → subsequent saves do not auto-insert. |
| **Edge cases** | Quick Draft; Gutenberg vs Classic; autosave/revisions should not corrupt. |
| **Failure cases** | Infinite save loops; links on every revision; disabled setting still inserts. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

---

# 9. Link Graph

### TC-LG-01 — Rebuild link graph

| | |
|---|---|
| **Preconditions** | Posts with internal and external links. |
| **Test steps** | Dashboard or Orphans → **Rebuild Link Graph**. Wait for completion. Inspect `{prefix}rsaip_link_graph` and post metrics. |
| **Expected result** | Graph rows for internal + external links. Metrics updated (`internal_out`, etc.). Dashboard tip about rebuild still accurate. |
| **Edge cases** | Large site — batch setting `link_graph_rebuild_batch`. Post with many external links — **multiple** rows with `to_post_id = 0` (Fix 2). |
| **Failure cases** | Only one external link stored per post; rebuild fatal; metrics zero when links exist. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-LG-02 — Broken link check cron path

| | |
|---|---|
| **Preconditions** | Graph has at least one intentionally broken external URL (404). Cron available or trigger via wp-cli `wp cron event run rsaip_cron_check_broken_links`. |
| **Test steps** | Run broken-link check. Inspect statuses in graph / audit UI. |
| **Expected result** | Broken URLs flagged without crashing. Timeout respects `broken_link_timeout_seconds`. |
| **Edge cases** | Redirecting URLs; slow endpoints near timeout. |
| **Failure cases** | Request hangs admin; marks all links broken; PHP timeout fatal. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-LG-03 — Orphan posts list

| | |
|---|---|
| **Preconditions** | Graph rebuilt; at least one post with zero internal in-links. |
| **Test steps** | Orphan Posts → Refresh (`rsaip_list_orphans`). |
| **Expected result** | Orphans listed; counts consistent with graph. |
| **Edge cases** | After adding internal links + rebuild, post leaves orphan list. |
| **Failure cases** | Empty list when orphans exist; includes non-published wrongly (per product rules). |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

---

# 10. SEO Audit

### TC-AUD-01 — Full audit + issues list

| | |
|---|---|
| **Preconditions** | Link graph rebuilt; posts with thin content / missing titles as test fixtures helpful. |
| **Test steps** | SEO Audit → **Run Audit** → **Refresh** issues table. |
| **Expected result** | Issues listed with scores/metrics persisted in `rsaip_post_metrics`. Re-run updates rows. |
| **Edge cases** | `thin_content_min_words` threshold changes → audit classification changes on re-run. |
| **Failure cases** | Audit fatal; metrics not written; UI spinner forever. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

---

# 11. Image SEO

### TC-IMG-01 — Scan image issues

| | |
|---|---|
| **Preconditions** | Posts/media with missing alt and/or large images. |
| **Test steps** | Image SEO → set large threshold KB → **Scan Images**. |
| **Expected result** | Table lists missing alt / oversized images per threshold. Inline threshold save works for non-secret. |
| **Edge cases** | Threshold 50 vs 5000. SVG / external images behavior as implemented. |
| **Failure cases** | Scan fatal; wrong size calculation; blank table with known bad images. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-IMG-02 — Generate alt text

| | |
|---|---|
| **Preconditions** | Image issues listed; AI configured (or heuristics if AI off). |
| **Test steps** | Generate alt text for an image/post via UI (`rsaip_generate_alt_text`). |
| **Expected result** | Alt text applied or returned successfully; attachment/post updated as designed. |
| **Edge cases** | AI off → fallback text still usable. |
| **Failure cases** | Wipes existing good alt; broken attachment metadata. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

---

# 12. Recipe Optimizer

### TC-RCP-01 — Scan recipe posts

| | |
|---|---|
| **Preconditions** | Posts that look like recipes (ingredients/instructions patterns or existing cards). |
| **Test steps** | Recipe Optimizer → **Scan Recipe Posts**. |
| **Expected result** | Table of candidates/status without fatals. |
| **Edge cases** | Non-recipe posts should not all appear as perfect recipes. |
| **Failure cases** | Empty scan on known recipe content; SQL errors. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-RCP-02 — Generate + insert recipe card

| | |
|---|---|
| **Preconditions** | Target post ID from scan; backup post content. |
| **Test steps** | Generate card → preview → Insert card into post. View front-end post. |
| **Expected result** | Card HTML inserted correctly (`wp_slash` path — quotes/special chars preserved). Markers/classes present for public rating assets (`RSAIP_RECIPE_CARD_START` or `rsaip-recipe-card`). Front-end renders. |
| **Edge cases** | Content with apostrophes, JSON-LD, unicode. Insert twice — no catastrophic duplication policy violation (document actual behavior). |
| **Failure cases** | Slashes corrupted (`\"` visible); content truncated; insert fails silently; editor shows raw broken HTML. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

---

# 13. Public Ratings

### TC-RATE-01 — Guest rating success

| | |
|---|---|
| **Preconditions** | Published post with recipe card; cookies allowed; votes table ready; logged out. |
| **Test steps** | Open front-end post → submit rating 1–5. Confirm UI updates average/count. |
| **Expected result** | AJAX `rsaip_public_save_recipe_rating` succeeds. Row in `rsaip_recipe_votes`. Post meta aggregates `rsaip_recipe_rating_value` / `rsaip_recipe_review_count` updated. Cookie `rsaip_voter` set for guests. |
| **Edge cases** | Logged-in user rating (different voter key). Mobile browser. |
| **Failure cases** | 403 nonce; rating not stored; meta not updated; requires `manage_options` (regression of Fix 3). |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-RATE-02 — Duplicate vote rejected

| | |
|---|---|
| **Preconditions** | Same voter already rated the post. |
| **Test steps** | Submit another rating. |
| **Expected result** | `409` — already rated. Aggregates unchanged. |
| **Edge cases** | Concurrent double-click — still one vote (unique key). |
| **Failure cases** | Second vote accepted; average skewed. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-RATE-03 — Abuse / validation guards

| | |
|---|---|
| **Preconditions** | Recipe post; DevTools to craft requests. |
| **Test steps** | 1. Invalid rating `0` or `6`. 2. Fill honeypot field `website`. 3. Draft/private post_id. 4. Spam many requests (rate limit). 5. Bad nonce. |
| **Expected result** | `400` invalid rating; honeypot `400`; unpublished `404`; rate limit `429`; bad nonce `403`. |
| **Edge cases** | Votes table missing → migrate attempt; if still missing → `503`. |
| **Failure cases** | Bots can vote via honeypot; unlimited spam; draft posts ratable. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

---

# 14. Schema

### TC-SCH-01 — Scan + single/batch fix

| | |
|---|---|
| **Preconditions** | Posts with missing/incomplete schema fixtures. |
| **Test steps** | Schema Validator → Scan → Auto Fix Common Issues → optionally Fix single post. |
| **Expected result** | Issues listed; fixes applied where supported; result panel updates. Post content/meta not destroyed. |
| **Edge cases** | Posts that already have valid schema — minimal/no harmful changes. |
| **Failure cases** | Invalid JSON-LD injected; content wipe; fatals. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-SCH-02 — Schema queue enqueue / process / stats

| | |
|---|---|
| **Preconditions** | Multiple posts needing fixes. |
| **Test steps** | Queue Auto Fix All → Process Schema Queue → Schema Queue Stats. |
| **Expected result** | Queue grows then shrinks; stats reflect pending/done; process respects limit params. |
| **Edge cases** | Process with empty queue — clean zero stats. |
| **Failure cases** | Queue stuck; duplicate endless jobs; stats wrong. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

---

# 15. Google Search Console

### TC-GSC-01 — Connection test

| | |
|---|---|
| **Preconditions** | Valid GSC property URL; service account email added in GSC; valid private key saved (leave blank to keep). |
| **Test steps** | Save GSC settings → **Test Connection**. |
| **Expected result** | Success response when credentials valid. Secret PEM not shown in HTML. |
| **Edge cases** | Wrong property URL; revoked key; leave PEM blank after save — connection still works. |
| **Failure cases** | False success; PEM wiped on save; key visible in page source. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-GSC-02 — Top opportunities + Low Hanging Fruits

| | |
|---|---|
| **Preconditions** | Working GSC connection; property has search analytics data. |
| **Test steps** | Load Top Opportunities. Open Low Hanging Fruits → Find Opportunities. |
| **Expected result** | Tables populate with queries/pages/metrics. Respects `gsc_lookback_days`. |
| **Edge cases** | Brand-new property with zero data — empty success, not fatal. Token cache (`rsaip_gsc_token` transient). |
| **Failure cases** | Fatals; empty error with valid data; token never refreshes. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

---

# 16. Performance

### TC-PERF-01 — Analyze URL (PSI)

| | |
|---|---|
| **Preconditions** | Valid `psi_api_key` saved; public URL reachable by Google PSI. |
| **Test steps** | Performance page → enter URL → mobile/desktop → **Analyze**. |
| **Expected result** | Scores/metrics rendered in result panel. Secret key not in HTML. |
| **Edge cases** | Invalid URL; API quota exceeded — readable error. Leave key blank on save — key retained. |
| **Failure cases** | Key wiped; uncaught API error; analyze hangs UI permanently. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

---

# 17. Reports

### TC-REP-01 — Preview

| | |
|---|---|
| **Preconditions** | Audit metrics exist (run audit first). |
| **Test steps** | Reports → Preview (`rsaip_report_preview`). |
| **Expected result** | Preview data loads (scores/issues summary) without fatals. |
| **Edge cases** | No metrics yet — empty/helpful state. |
| **Failure cases** | SQL error; blank fatal. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-REP-02 — Export CSV

| | |
|---|---|
| **Preconditions** | Preview works. |
| **Test steps** | Export CSV; open file. |
| **Expected result** | Downloadable CSV with expected columns/rows. |
| **Edge cases** | Large result set. |
| **Failure cases** | Empty file; HTML error page downloaded as CSV; encoding broken. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-REP-03 — Export XLSX

| | |
|---|---|
| **Preconditions** | PHP `ZipArchive` available preferred. |
| **Test steps** | Export XLSX; open in Excel/Sheets. |
| **Expected result** | Valid spreadsheet **or** documented fallback to CSV if ZipArchive missing / empty builder. |
| **Edge cases** | Host without ZipArchive — must fallback cleanly, not fatal. |
| **Failure cases** | Corrupt zip; fatal; silent zero-byte file. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-REP-04 — Export PDF

| | |
|---|---|
| **Preconditions** | PDF exporter class present. |
| **Test steps** | Export PDF; open file. |
| **Expected result** | Readable PDF **or** CSV fallback if PDF unavailable. ASCII/Helvetica limits may affect unicode — note if garbled (pre-existing). |
| **Edge cases** | Non-Latin titles in report. |
| **Failure cases** | Fatal during stream; truncated download. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

---

# 18. Bulk Optimizer

### TC-BULK-01 — Enqueue, process, stats (UI)

| | |
|---|---|
| **Preconditions** | AI page open (bulk UI lives there); posts to optimize; optional AI config. |
| **Test steps** | Enqueue fix queue → view stats → Process queue (limit). Inspect `rsaip_bulk_queue` rows. |
| **Expected result** | Jobs move pending → processing/done/failed appropriately. `bulk_queue_batch_size` respected. Stats AJAX accurate. |
| **Edge cases** | Empty enqueue; re-process completed queue. |
| **Failure cases** | Duplicate infinite enqueue; process fatal mid-batch; stats stuck. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-BULK-02 — Cron processes queue

| | |
|---|---|
| **Preconditions** | Pending jobs in bulk queue; plugin active. |
| **Test steps** | Run `wp cron event run rsaip_cron_process_bulk_queue` (or wait for hourly cron). |
| **Expected result** | Some/all pending jobs processed without admin UI. Same outcomes as manual process. |
| **Edge cases** | Deactivated plugin — cron cleared, queue data retained. |
| **Failure cases** | Cron no-ops forever; fatals in cron context only. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

---

# 19. Cron Jobs

### TC-CRON-01 — All three hooks registered when active

| | |
|---|---|
| **Preconditions** | Plugin active post-activation. |
| **Test steps** | `wp cron event list` (or Cron Manager plugin). |
| **Expected result** | Events exist: `rsaip_cron_rebuild_link_graph`, `rsaip_cron_check_broken_links`, `rsaip_cron_process_bulk_queue`. |
| **Edge cases** | Disable WP-Cron (`DISABLE_WP_CRON`) — events still scheduled; system cron must trigger `wp-cron.php`. |
| **Failure cases** | Missing events after activate; duplicate dozens of same hook. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-CRON-02 — Handlers execute safely

| | |
|---|---|
| **Preconditions** | Graph + optional bulk jobs. |
| **Test steps** | Run each hook via wp-cli. Check `debug.log`. |
| **Expected result** | Each completes; graph/metrics/queue updated as applicable; no fatals. |
| **Edge cases** | Run twice back-to-back. |
| **Failure cases** | Memory exhaustion; deadlocks; uncaught errors. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-CRON-03 — Cleared on deactivate

| | |
|---|---|
| **Preconditions** | Crons present. |
| **Test steps** | Deactivate → list events. |
| **Expected result** | All three `rsaip_cron_*` cleared. |
| **Edge cases** | — |
| **Failure cases** | Hooks remain and call missing classes. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

---

# 20. Repository Layer

> Smoke tests that repository wiring did not break SQL-backed features. No code changes.

### TC-REPO-01 — Link graph + metrics repositories

| | |
|---|---|
| **Preconditions** | Plugin active; Query Monitor or `$wpdb->queries` optional. |
| **Test steps** | Rebuild link graph → run audit → list orphans. |
| **Expected result** | Same functional outcomes as pre-2B. No “table not found”. No TypeErrors from strict types. |
| **Edge cases** | Large batch rebuild. |
| **Failure cases** | Fatals in `RecipeSeoAiPro\Database\Repositories\*`; empty metrics after successful UI “success”. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-REPO-02 — Suggestions + bulk repositories

| | |
|---|---|
| **Preconditions** | Posts available. |
| **Test steps** | Generate suggestions; enqueue/process bulk job. |
| **Expected result** | Rows written/read in `rsaip_link_suggestions` and `rsaip_bulk_queue`. |
| **Edge cases** | — |
| **Failure cases** | Inserts fail; list empty after generate success message. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-REPO-03 — RecipeVoteRepository intentionally unused

| | |
|---|---|
| **Preconditions** | Awareness that public ratings still use `$wpdb` in AJAX. |
| **Test steps** | Complete TC-RATE-01. Confirm votes table rows written (via AJAX path). |
| **Expected result** | Ratings work end-to-end even though `RecipeVoteRepository` is not called. Document as known Phase 2B boundary (not a defect unless ratings fail). |
| **Edge cases** | — |
| **Failure cases** | Ratings broken because someone expected repository wiring — treat as product bug if AJAX path fails. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-REPO-04 — Facade without broken DI

| | |
|---|---|
| **Preconditions** | Normal boot. |
| **Test steps** | Load Dashboard (uses repos indirectly). Optionally confirm no fatals if temporarily renaming is not done — just smoke admin AJAX. |
| **Expected result** | `rsaip_repo()` resolves classes; container has bindings after `Plugin::boot()`. |
| **Edge cases** | — |
| **Failure cases** | `Class not found`; container `has()` false causing bad fallback instances with wrong state. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

---

# 21. AI Provider Layer

### TC-AIP-01 — HTTP transport used for live AI

| | |
|---|---|
| **Preconditions** | AI configured; Network tab or server outbound log. |
| **Test steps** | Generate Titles with AI on. Inspect outbound POST: endpoint, `Authorization: Bearer …`, JSON body with `model`, `temperature: 0.2`, `max_tokens`, `messages[0].role=user`. |
| **Expected result** | Request shape matches OpenAI-compatible Chat Completions. Response parsed into titles. Errors map to graceful UI/fallback. |
| **Edge cases** | Provider returns `choices[0].text` instead of `message.content` — still accepted. |
| **Failure cases** | Wrong URL; missing auth; temperature changed; max_tokens not clamped 64–1200; parse fatal. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-AIP-02 — Settings-driven config still from `rsaip_settings`

| | |
|---|---|
| **Preconditions** | Ability to change endpoint/model/timeout. |
| **Test steps** | Change model + timeout → save → regenerate. |
| **Expected result** | New model appears in request; timeout honored (slow endpoint). Keys unchanged if left blank. |
| **Edge cases** | Timeout at min 5 / max 60 after sanitize. |
| **Failure cases** | Provider ignores settings; uses hardcoded endpoint. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-AIP-03 — Disabled provider setting vs chat() historical behavior

| | |
|---|---|
| **Preconditions** | Understand Phase 2C contract: UI/`use_ai()` gates on `ai_provider`; `chat()` always uses `openai_compatible` transport if invoked. |
| **Test steps** | 1. Set provider `disabled`, keep key — use normal AI UI buttons. 2. Confirm heuristics/no live call from UI. |
| **Expected result** | Normal UI does not produce live completions when `use_ai()` is false. No fatals from `DisabledProvider` in default UI paths. |
| **Edge cases** | Document only: direct `chat()` invocation with disabled provider + keys would still HTTP (historical). Not a UI regression if UI gates correctly. |
| **Failure cases** | UI still calls API when provider disabled; fatals resolving registry. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-AIP-04 — Registry / autoload smoke

| | |
|---|---|
| **Preconditions** | No `vendor/` preferred for this case. |
| **Test steps** | Load AI page; run one AI action. |
| **Expected result** | `OpenAiCompatibleProvider` / registry autoload successfully. No “Class RecipeSeoAiPro\Modules\Ai\… not found”. |
| **Edge cases** | With Composer vendor present — also works. |
| **Failure cases** | Autoload miss; wrong namespace path. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

---

# Cross-cutting smoke (optional but recommended)

### TC-X-01 — Admin menu surface

Visit every submenu once: Dashboard, Internal Linking, Auto Linking, Orphans, Audit, Image SEO, GSC, Low Hanging, Recipe Optimizer, Schema, Sitemap, Performance, AI, Reports, Settings.

| **Expected** | Each loads without PHP fatal/JS hard error. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-X-02 — Sitemap Auditor

| | |
|---|---|
| **Preconditions** | Site has a sitemap URL (Yoast/RankMath/core). |
| **Test steps** | Sitemap Auditor → enter URL → Audit. |
| **Expected result** | Rows of findings or clean empty success; invalid URL handled. |
| **Edge cases** | Sitemap index vs single sitemap. |
| **Failure cases** | Fatal on fetch; SSRF to internal IPs if applicable. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

### TC-X-03 — Dashboard cards

| | |
|---|---|
| **Preconditions** | Graph + audit run. |
| **Test steps** | Refresh Dashboard stats. |
| **Expected result** | Cards populate; rebuild/audit buttons work. |
| **Result** | ☐ Pass ☐ Fail ☐ Blocked ☐ N/A |

---

# Sign-off

| Role | Name | Date | Signature / ack |
|------|------|------|-----------------|
| Tester | | | |
| Reviewer | | | |

### Summary counts

| Severity area | Pass | Fail | Blocked | N/A |
|---------------|------|------|---------|-----|
| Activation / Upgrade / Deactivate / Uninstall | | | | |
| Settings / Secrets | | | | |
| Linking / Graph / Audit | | | | |
| Recipe / Ratings / Schema | | | | |
| GSC / Performance / Reports | | | | |
| AI / Bulk / Cron | | | | |
| Repository / AI Provider | | | | |
| **Totals** | | | | |

### Go / No-Go for Phase 2D

| Decision | ☐ GO — proceed to Phase 2D planning/approval | ☐ NO-GO — fix failures first |
|----------|-----------------------------------------------|------------------------------|
| **Blocking failures (IDs)** | | |
| **Waived items + reason** | | |
| **Notes** | | |

---

*End of QA document. Generated for QA Phase after Phases 1–2C. Do not use this document as authorization to refactor code.*
