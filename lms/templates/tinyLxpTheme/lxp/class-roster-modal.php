<?php
/**
 * Class roster modal — token students (Zone A) + encrypted name map (Zone B).
 *
 * Locked, this shows only non-PII seat labels. Unlocked with the teacher's
 * passphrase, real names are decrypted **in this browser** and rendered
 * alongside them; the server never sees a name or a key.
 *
 * The CSV import parses entirely client-side: names go into the encrypted
 * vault, only seat labels are ever uploaded, and the file never touches disk.
 *
 * Included by admin-classes.php and teacher-classes.php.
 *
 * @see docs/student-privacy-zone-b-context.md
 */
?>
<div class="modal fade" id="lxpRosterModal" tabindex="-1" aria-labelledby="lxpRosterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="lxpRosterModalLabel">Class Roster</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">

                <p class="text-muted" style="font-size:13px">
                    Students join with a seat label, never their real name. Real names are stored
                    encrypted and can only be read on this device, after you enter your passphrase.
                </p>

                <div id="lxp-roster-summary" style="margin-bottom:12px;font-size:13px"></div>
                <div id="lxp-roster-error" class="alert alert-danger" style="display:none"></div>
                <div id="lxp-roster-notice" class="alert alert-warning" style="display:none;font-size:13px"></div>

                <!-- Vault: locked state -->
                <div id="lxp-vault-locked" style="display:none;border:1px solid #dadce0;border-radius:6px;padding:12px;margin-bottom:16px">
                    <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
                        <div style="flex:1;min-width:200px">
                            <label for="lxp-vault-pass" style="display:block;font-size:12px;color:#5f6368">
                                <span id="lxp-vault-pass-label">Roster passphrase</span>
                            </label>
                            <input type="password" id="lxp-vault-pass" class="form-control" autocomplete="off">
                        </div>
                        <div id="lxp-vault-confirm-wrap" style="flex:1;min-width:200px;display:none">
                            <label for="lxp-vault-pass2" style="display:block;font-size:12px;color:#5f6368">Confirm passphrase</label>
                            <input type="password" id="lxp-vault-pass2" class="form-control" autocomplete="off">
                        </div>
                        <button type="button" class="btn btn-primary" id="lxp-vault-unlock">Show names</button>
                    </div>
                    <small id="lxp-vault-hint" class="text-muted" style="display:block;margin-top:8px;font-size:12px"></small>
                </div>

                <!-- Vault: unlocked state -->
                <div id="lxp-vault-unlocked" style="display:none;margin-bottom:16px">
                    <span class="badge" style="background:#e6f4ea;color:#137333;padding:4px 10px;border-radius:10px;font-size:12px">Names visible</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="lxp-vault-lock">Hide names</button>
                    <button type="button" class="btn btn-sm btn-primary" id="lxp-vault-save" style="display:none">Save names</button>
                    <label class="btn btn-sm btn-outline-primary" style="margin-bottom:0">
                        Import roster CSV
                        <input type="file" id="lxp-roster-csv" accept=".csv,text/csv" style="display:none">
                    </label>
                </div>

                <!-- Create seats -->
                <div style="display:flex;gap:8px;align-items:flex-end;margin-bottom:16px;flex-wrap:wrap">
                    <div>
                        <label for="lxp-roster-seat-count" style="display:block;font-size:12px;color:#5f6368">Add seats</label>
                        <input type="number" min="1" max="100" step="1" value="5" id="lxp-roster-seat-count" class="form-control" style="width:110px">
                    </div>
                    <button type="button" class="btn btn-outline-primary" id="lxp-roster-add-seats">Create seats</button>
                    <button type="button" class="btn btn-outline-secondary" id="lxp-roster-print">Print claim slips</button>
                </div>

                <div id="lxp-roster-table-wrap" style="overflow-x:auto">
                    <table class="table" id="lxp-roster-table">
                        <thead>
                            <tr>
                                <th style="font-size:12px">Seat</th>
                                <th style="font-size:12px" class="lxp-name-col" hidden>Real name</th>
                                <th style="font-size:12px">Joined</th>
                                <th style="font-size:12px">Added</th>
                                <th style="font-size:12px">Last seen</th>
                                <th style="font-size:12px">Claim link</th>
                            </tr>
                        </thead>
                        <tbody id="lxp-roster-rows"></tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="<?php echo esc_url( TL_PLUGIN_URL . 'includes/widgets/assets/js/lxp-roster-vault.js' ); ?>"></script>
<script>
(function () {
    var modalObj   = null;
    var classId    = 0;
    var vault      = null;
    var roster     = [];
    var dirty      = false;
    // Claim links exist in plaintext only at the moment they are minted — the
    // server keeps hashes. Hold this session's in memory for printing.
    var claimLinks = {};

    function apiUrl() {
        var host = window.location.hostname === 'localhost'
            ? window.location.origin + '<?php echo WORDPRESS_HOST; ?>'
            : window.location.origin;
        return host + '/wp-json/lms/v1/';
    }

    function esc(str) {
        return String(str === null || str === undefined ? '' : str).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function showError(msg) {
        var box = document.getElementById('lxp-roster-error');
        box.textContent = msg || '';
        box.style.display = msg ? 'block' : 'none';
    }

    function showNotice(msg) {
        var box = document.getElementById('lxp-roster-notice');
        box.innerHTML = msg || '';
        box.style.display = msg ? 'block' : 'none';
    }

    function post(path, data) {
        var body = new FormData();
        Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
        return jQuery.ajax({ method: 'POST', url: apiUrl() + path, data: body,
                             processData: false, contentType: false });
    }

    // -----------------------------------------------------------------
    // Rendering
    // -----------------------------------------------------------------

    function renderRoster(data) {
        var tbody = document.getElementById('lxp-roster-rows');
        tbody.innerHTML = '';
        roster = data.roster || [];

        var seats = data.max_seats > 0
            ? (data.seats_taken + ' / ' + data.max_seats)
            : (data.seats_taken + ' / unlimited');

        document.getElementById('lxp-roster-summary').innerHTML =
            '<strong>Code:</strong> <code>' + esc(data.class_code || '—') + '</code>' +
            ' &nbsp;·&nbsp; <strong>Seats used:</strong> ' + esc(seats);

        var unlocked = vault && vault.isUnlocked();
        document.querySelectorAll('.lxp-name-col').forEach(function (th) { th.hidden = !unlocked; });

        if (!roster.length) {
            tbody.innerHTML = '<tr><td colspan="6" style="color:#5f6368">No students have joined yet.</td></tr>';
            return;
        }

        roster.forEach(function (row) {
            var link = claimLinks[row.id];
            var tr   = document.createElement('tr');

            var nameCell = '';
            if (unlocked) {
                nameCell = '<td><input type="text" class="form-control form-control-sm lxp-name-input" ' +
                           'data-member-id="' + row.id + '" value="' + esc(vault.getName(row.id)) + '" ' +
                           'placeholder="Not set" style="font-size:13px"></td>';
            }

            tr.innerHTML =
                '<td><strong>' + esc(row.alias_label) + '</strong></td>' +
                nameCell +
                '<td style="font-size:12px">' + esc(row.joined_via) + '</td>' +
                '<td style="font-size:12px">' + esc((row.created_at || '').slice(0, 10)) + '</td>' +
                '<td style="font-size:12px">' + esc(row.last_seen ? row.last_seen.slice(0, 10) : '—') + '</td>' +
                '<td style="white-space:nowrap">' +
                    (link
                        ? '<input type="text" readonly value="' + esc(link) + '" style="width:200px;font-size:11px" onclick="this.select()">'
                        : '<button type="button" class="btn btn-sm btn-outline-primary lxp-reissue" data-member-id="' + row.id + '">Issue link</button>'
                    ) +
                '</td>';
            tbody.appendChild(tr);
        });
    }

    function renderVaultState() {
        var locked   = document.getElementById('lxp-vault-locked');
        var unlocked = document.getElementById('lxp-vault-unlocked');
        var hint     = document.getElementById('lxp-vault-hint');
        var confirm  = document.getElementById('lxp-vault-confirm-wrap');
        var btn      = document.getElementById('lxp-vault-unlock');

        if (vault && vault.isUnlocked()) {
            locked.style.display   = 'none';
            unlocked.style.display = '';
            return;
        }

        locked.style.display   = '';
        unlocked.style.display = 'none';

        if (vault && vault.exists) {
            confirm.style.display = 'none';
            btn.textContent = 'Show names';
            hint.textContent = 'Enter the passphrase you chose for this class.';
        } else {
            confirm.style.display = '';
            btn.textContent = 'Set up name storage';
            hint.innerHTML = 'Choose a passphrase for this class. <strong>It is never sent to the server ' +
                             'and cannot be reset by us.</strong>' +
                             (vault && vault.escrowPem
                                ? ' Your district has a recovery key, so they can restore access if you forget it.'
                                : ' <strong>Your district has not set a recovery key, so if you forget this passphrase these names are gone permanently.</strong>');
        }
    }

    // -----------------------------------------------------------------
    // Loading
    // -----------------------------------------------------------------

    function loadRoster() {
        showError('');
        return post('class/roster', { class_id: classId })
            .done(function (response) { renderRoster(response.data); })
            .fail(function (response) {
                showError((response.responseJSON && response.responseJSON.data) || 'Could not load the roster.');
            });
    }

    function loadVault() {
        vault = new window.LXPRosterVault({ apiUrl: apiUrl(), classId: classId });
        return vault.load().then(function () {
            if (vault.exists && !vault.hasEscrow && vault.escrowPem) {
                showNotice('This roster has no district recovery copy yet. It will be added the next time you save names.');
            }
            renderVaultState();
        }).catch(function () {
            showError('Could not reach the roster vault.');
        });
    }

    // -----------------------------------------------------------------
    // CSV — parsed entirely in this browser
    // -----------------------------------------------------------------

    /** Minimal RFC-4180-ish parser: handles quoted fields and embedded commas. */
    function parseCsv(text) {
        var rows = [], row = [], field = '', inQuotes = false;

        for (var i = 0; i < text.length; i++) {
            var c = text[i];
            if (inQuotes) {
                if (c === '"') {
                    if (text[i + 1] === '"') { field += '"'; i++; }
                    else { inQuotes = false; }
                } else { field += c; }
            } else if (c === '"') {
                inQuotes = true;
            } else if (c === ',') {
                row.push(field); field = '';
            } else if (c === '\n') {
                row.push(field); field = '';
                if (row.some(function (f) { return f.trim() !== ''; })) { rows.push(row); }
                row = [];
            } else if (c !== '\r') {
                field += c;
            }
        }
        row.push(field);
        if (row.some(function (f) { return f.trim() !== ''; })) { rows.push(row); }

        return rows;
    }

    // Columns that must never be uploaded or stored, in either zone.
    var BANNED_HEADERS = [
        'email', 'e-mail', 'dob', 'date_of_birth', 'birthdate', 'birthday',
        'phone', 'address', 'gender', 'ethnicity', 'race', 'iep', 'accommodation',
        'accommodations', 'ell', 'esl', 'frl', 'free_reduced', 'lunch',
        'ssn', 'student_id', 'sis_id', 'parent_email', 'guardian_email'
    ];

    function handleCsv(file) {
        showError('');

        if (!vault || !vault.isUnlocked()) {
            showError('Unlock the roster first — names have to be encrypted before they can be stored.');
            return;
        }

        var reader = new FileReader();
        reader.onload = function (e) {
            var rows = parseCsv(String(e.target.result));
            if (!rows.length) { showError('That file appears to be empty.'); return; }

            var header = rows[0].map(function (h) { return h.trim().toLowerCase(); });
            var hasHeader = header.indexOf('first_name') !== -1 || header.indexOf('last_name') !== -1;

            // Refuse special-category and identifier columns outright rather
            // than silently ignoring them — a teacher must know they were seen.
            if (hasHeader) {
                var banned = header.filter(function (h) { return BANNED_HEADERS.indexOf(h) !== -1; });
                if (banned.length) {
                    showError('This file contains columns we will not accept: ' + banned.join(', ') +
                              '. Remove them and upload only first_name, last_name and an optional alias_label.');
                    return;
                }
            }

            var body = hasHeader ? rows.slice(1) : rows;
            var aliasIdx = hasHeader ? header.indexOf('alias_label') : -1;

            var people = [];
            body.forEach(function (r) {
                var first = (r[0] || '').trim();
                var last  = (r[1] || '').trim();
                if (!first && !last) { return; }
                people.push({
                    name:  (first + ' ' + last).trim(),
                    alias: aliasIdx > -1 ? (r[aliasIdx] || '').trim() : ''
                });
            });

            if (!people.length) { showError('No usable rows found.'); return; }

            if (!window.confirm(
                people.length + ' student(s) found.\n\n' +
                'Their names stay in this browser and will be encrypted before storage. ' +
                'Only seat labels are sent to the server.\n\nContinue?'
            )) { return; }

            provisionFromCsv(people);
        };
        reader.onerror = function () { showError('Could not read that file.'); };
        reader.readAsText(file);
    }

    function provisionFromCsv(people) {
        var explicit = people.filter(function (p) { return p.alias; }).map(function (p) { return p.alias; });

        var payload = { class_id: classId };
        if (explicit.length === people.length) {
            people.forEach(function (p, i) { payload['aliases[' + i + ']'] = p.alias; });
        } else {
            payload.seat_count = people.length;
        }

        post('class/roster/provision', payload).done(function (response) {
            var created = response.data.created || [];

            // Pair each new seat with the name from the same row, in order.
            created.forEach(function (seat, i) {
                claimLinks[seat.member_id] = seat.claim_url;
                if (people[i]) { vault.setName(seat.member_id, people[i].name); }
            });

            if (response.data.skipped && response.data.skipped.length) {
                showError(response.data.skipped.length + ' row(s) were skipped (class may be full).');
            }

            var pass = document.getElementById('lxp-vault-pass').value;
            return vault.save(pass, false).then(function () {
                dirty = false;
                return loadRoster();
            });
        }).fail(function (response) {
            showError((response.responseJSON && response.responseJSON.data) || 'Could not create seats.');
        });
    }

    // -----------------------------------------------------------------
    // Wiring
    // -----------------------------------------------------------------

    document.addEventListener('DOMContentLoaded', function () {
        var el = document.getElementById('lxpRosterModal');
        if (!el || typeof bootstrap === 'undefined') { return; }
        modalObj = new bootstrap.Modal(el);

        document.querySelectorAll('.lxp-view-roster').forEach(function (btn) {
            btn.addEventListener('click', function () {
                classId    = parseInt(btn.getAttribute('data-class-id'), 10) || 0;
                claimLinks = {};
                dirty      = false;
                showError(''); showNotice('');
                document.getElementById('lxp-vault-pass').value  = '';
                document.getElementById('lxp-vault-pass2').value = '';
                document.getElementById('lxpRosterModalLabel').textContent =
                    'Roster — ' + (btn.getAttribute('data-class-name') || 'Class');

                loadVault().then(loadRoster);
                modalObj.show();
            });
        });

        // Always drop the key when the modal closes.
        el.addEventListener('hide.bs.modal', function () {
            if (vault) { vault.lock(); }
            document.getElementById('lxp-vault-pass').value  = '';
            document.getElementById('lxp-vault-pass2').value = '';
            renderVaultState();
        });

        // --- Unlock / create -------------------------------------------
        document.getElementById('lxp-vault-unlock').addEventListener('click', function () {
            var btn  = this;
            var pass = document.getElementById('lxp-vault-pass').value;
            if (!pass) { showError('Enter a passphrase.'); return; }

            showError('');
            btn.disabled = true;
            btn.textContent = 'Working…';

            var done = function () {
                btn.disabled = false;
                renderVaultState();
                renderRoster({
                    roster: roster,
                    seats_taken: roster.length,
                    max_seats: 0,
                    class_code: null
                });
                loadRoster();
            };

            if (vault.exists) {
                vault.unlock(pass).then(done).catch(function (err) {
                    showError(err.message);
                    btn.disabled = false;
                    renderVaultState();
                });
            } else {
                var pass2 = document.getElementById('lxp-vault-pass2').value;
                if (pass !== pass2) {
                    showError('The two passphrases do not match.');
                    btn.disabled = false;
                    renderVaultState();
                    return;
                }
                if (pass.length < 10) {
                    showError('Please use at least 10 characters — this is the only thing protecting the names.');
                    btn.disabled = false;
                    renderVaultState();
                    return;
                }
                vault.create(pass, {}).then(done).catch(function (err) {
                    showError(err.message || 'Could not set up name storage.');
                    btn.disabled = false;
                    renderVaultState();
                });
            }
        });

        document.getElementById('lxp-vault-lock').addEventListener('click', function () {
            if (dirty && !window.confirm('You have unsaved name changes. Hide names anyway?')) { return; }
            vault.lock();
            document.getElementById('lxp-vault-pass').value = '';
            dirty = false;
            renderVaultState();
            loadRoster();
        });

        // --- Name editing ----------------------------------------------
        document.addEventListener('input', function (e) {
            if (!e.target.classList || !e.target.classList.contains('lxp-name-input')) { return; }
            vault.setName(e.target.getAttribute('data-member-id'), e.target.value);
            dirty = true;
            document.getElementById('lxp-vault-save').style.display = '';
        });

        document.getElementById('lxp-vault-save').addEventListener('click', function () {
            var btn  = this;
            var pass = document.getElementById('lxp-vault-pass').value;
            if (!pass) {
                showError('Re-enter your passphrase to save (it is not kept in memory).');
                return;
            }
            btn.disabled = true;
            vault.save(pass, false).then(function () {
                dirty = false;
                btn.style.display = 'none';
                btn.disabled = false;
                showError('');
            }).catch(function (err) {
                showError(err.message);
                btn.disabled = false;
            });
        });

        // --- CSV --------------------------------------------------------
        document.getElementById('lxp-roster-csv').addEventListener('change', function (e) {
            if (e.target.files && e.target.files[0]) { handleCsv(e.target.files[0]); }
            e.target.value = '';
        });

        // --- Seats ------------------------------------------------------
        document.getElementById('lxp-roster-add-seats').addEventListener('click', function () {
            var count = parseInt(document.getElementById('lxp-roster-seat-count').value, 10) || 0;
            if (count < 1) { return; }

            showError('');
            var btn = this;
            btn.disabled = true;

            post('class/roster/provision', { class_id: classId, seat_count: count })
                .done(function (response) {
                    (response.data.created || []).forEach(function (item) {
                        if (item.member_id) { claimLinks[item.member_id] = item.claim_url; }
                    });
                    if (response.data.skipped && response.data.skipped.length) {
                        showError(response.data.skipped.length + ' seat(s) could not be created (class may be full).');
                    }
                    loadRoster();
                })
                .fail(function (response) {
                    showError((response.responseJSON && response.responseJSON.data) || 'Could not create seats.');
                })
                .always(function () { btn.disabled = false; });
        });

        // --- Claim links ------------------------------------------------
        document.addEventListener('click', function (e) {
            var btn = e.target.closest && e.target.closest('.lxp-reissue');
            if (!btn) { return; }

            var memberId = parseInt(btn.getAttribute('data-member-id'), 10) || 0;
            btn.disabled = true;

            post('class/member/reissue', { member_id: memberId })
                .done(function (response) {
                    claimLinks[memberId] = response.data.claim_url;
                    loadRoster();
                })
                .fail(function (response) {
                    showError((response.responseJSON && response.responseJSON.data) || 'Could not issue a link.');
                    btn.disabled = false;
                });
        });

        document.getElementById('lxp-roster-print').addEventListener('click', function () {
            var rows = document.querySelectorAll('#lxp-roster-rows tr');
            var html = '<h2>Claim slips</h2>';
            var any  = false;

            rows.forEach(function (tr) {
                var alias = tr.querySelector('td strong');
                var input = tr.querySelector('td:last-child input[type="text"]');
                if (!alias || !input) { return; }
                any = true;

                // Include the real name only while the teacher has it unlocked —
                // these slips are handed to specific students.
                var nameInput = tr.querySelector('.lxp-name-input');
                var realName  = nameInput && nameInput.value ? nameInput.value : '';

                html += '<div style="border:1px dashed #999;padding:12px;margin:8px 0;page-break-inside:avoid">' +
                        (realName ? '<div style="font-size:16px">' + esc(realName) + '</div>' : '') +
                        '<div style="font-size:18px;font-weight:bold">' + esc(alias.textContent) + '</div>' +
                        '<div style="font-size:11px;word-break:break-all">' + esc(input.value) + '</div>' +
                        '</div>';
            });

            if (!any) {
                showError('Issue a claim link first — links can only be printed when they are created.');
                return;
            }

            var w = window.open('', '_blank');
            w.document.write('<html><head><title>Claim slips</title></head><body>' + html + '</body></html>');
            w.document.close();
            w.print();
        });
    });
})();
</script>
