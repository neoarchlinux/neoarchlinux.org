<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NeoArch ISO Builder</title>
    <link rel="stylesheet" href="/index.css">
    <style>
        main {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        h1 {
            text-align: center;
            margin-bottom: 2rem;
            font-weight: 600;
        }

        h2 {
            margin-top: 2rem;
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
            color: var(--muted);
            font-weight: 500;
        }

        abbr {
            cursor: help;
        }

        input {
            width: 100%;
            padding: 0.5rem;
            background: var(--bg-panel);
            border: 1px solid var(--border);
            color: var(--fg-text);
            border-radius: 4px;
            box-sizing: border-box;
        }

        input:focus {
            outline: none;
            border-color: var(--accent-primary);
        }

        input[type="checkbox"],
        input[type="radio"] {
            accent-color: var(--accent-primary);
            cursor: pointer;
        }

        input[type="checkbox"],
        input[type="radio"] {
            transform: scale(1.1);
        }

        select {
            width: 100%;
            padding: 0.5rem;
            background: var(--bg-panel);
            border: 1px solid var(--border);
            color: var(--fg-text);
            border-radius: 4px;
            box-sizing: border-box;

            font-family: inherit;
            font-size: 0.9rem;

            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;

            cursor: pointer;
        }

        select:focus {
            outline: none;
            border-color: var(--accent-primary);
        }

        select {
            background-image: url("data:image/svg+xml;utf8,<svg fill='%23aaa' height='20' viewBox='0 0 20 20' width='20' xmlns='http://www.w3.org/2000/svg'><path d='M5 7l5 5 5-5H5z'/></svg>");
            background-repeat: no-repeat;
            background-position: right 0.5rem center;
            background-size: 16px;
            padding-right: 2rem;
        }

        option {
            background: var(--bg-panel);
            color: var(--fg-text);
        }

        optgroup {
            color: var(--muted);
            font-style: normal;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--bg-nav);
            border: 1px solid var(--border);
            border-radius: 6px;
            overflow: hidden;
        }

        th, td {
            padding: 0.6rem;
            border-bottom: 1px solid var(--border);
            font-size: 0.9rem;
        }

        th {
            text-align: left;
            color: var(--muted);
            font-weight: 500;
        }

        tr:last-child td {
            border-bottom: none;
        }

        #additional-packages {
            table-layout: auto;
            width: 100%;
        }

        #additional-packages th:nth-child(1),
        #additional-packages td:nth-child(1) {
            text-align: left;
            width: 100%;
        }

        #additional-packages th:nth-child(2),
        #additional-packages th:nth-child(3),
        #additional-packages td:nth-child(2),
        #additional-packages td:nth-child(3) {
            width: 1%;
            white-space: nowrap;
            text-align: right;
        }

        button {
            background: var(--bg-nav);
            border: 1px solid var(--accent-primary);
            color: var(--fg-text);
            padding: 0.4rem 0.7rem;
            border-radius: 4px;
            cursor: pointer;
        }

        button:hover {
            border-color: var(--accent-secondary);
        }

        button:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        button[type="submit"] {
            margin-top: 2rem;
            width: 100%;
            padding: 0.7rem;
            font-weight: 500;
            border-color: var(--accent-ternary);
        }

        button[type="submit"]:hover {
            border-color: var(--accent-secondary);
        }

        #pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-top: 1rem;
        }

        .note {
            color: var(--muted);
            font-size: 0.8rem;
        }

        .iso-form > * {
            margin-bottom: 1rem;
        }

        .advanced-toggle {
            display: inline-block;
            margin-top: 1rem;
            cursor: pointer;
            color: var(--muted);
            font-size: 0.9rem;
        }

        .advanced-toggle:hover {
            color: var(--fg-text);
        }

        .advanced-content {
            margin-top: 1rem;
            padding: 1rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg-nav);
        }
    </style>
</head>
<body>

<?php require_once '/var/www/src/config.php'; ?>
<?php require_once '/var/www/src/header.php'; ?>

<main>
    <h1 style="text-align:center;">NeoArch ISO Builder</h1>

    <form method="POST" action="/build" class="iso-form" onsubmit="return validateForm()">

        <h2><abbr title="A unique label for your computer on your network — what your computer is named">Hostname</abbr></h2>
        <input type="text" name="hostname" value="neoarch" required><br>

        <h2><abbr title="System locale - controls language, formatting, and messages">Language</abbr></h2>
        <select name="language" required>
            <?php require_once '/var/www/src/heavy/options/language.php'; ?>
        </select>

        <h2><abbr title="Region / timezone used for synchronizing the system clock">Timezone</abbr></h2>
        <select name="timezone" required>
            <?php require_once '/var/www/src/heavy/options/timezone.php'; ?>
        </select>

        <h2><abbr title="User accounts that will be created on the system">Users</abbr></h2>
        <table id="users">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Password (optional)</th>
                    <th><abbr title="Add user to the sudoers - allow them to perform every action on the computer">Admin</abbr></th>
                    <th>Remove</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>

        <button type="button" onclick="addUserRow()">Add User</button><br>
        <small class="note">At least one user must be created (that may be root).</small><br>
        <small class="note">If no password is chosen now, you will be asked to choose it during the install.</small><br>
        <small class="note">Passwords are stored securely in the ISO and cannot be deduced.</small><br>

        <h2><abbr title="Additional software to include in the ISO">Additional packages</abbr></h2>
        <input type="text" id="search" placeholder="Search packages by name or description..." />

        <table id="package-table">
        <thead>
        <tr>
            <th>Name</th>
            <th>Description</th>
            <th>Add</th>
        </tr>
        </thead>
        <tbody>
            <tr><td colspan="5">Loading packages…</td></tr>
        </tbody>
        </table>

        <div id="pagination">
            <button type="button" id="prev-page" disabled>&#9664;&nbsp;&nbsp;Previous</button>
            <span id="page-info">Page 1</span>
            <button type="button" id="next-page" disabled>Next&nbsp;&nbsp;&#9654;</button>
        </div>

        <table id="additional-packages">
            <thead>
            <tr>
                <th>Name</th>
                <th><abbr title="If selected, this package will be accessible in the live ISO">Live</th>
                <th><abbr title="If selected, this package will be accessible in the installed system">System</th>
            </tr>
            </thead>
            <tbody>
                <tr><td>No addiitonal packages selected</td></tr>
            </tbody>
        </table>
        <br>

        <span class="advanced-toggle">Advanced options &blacktriangledown;</span>
        <div class="advanced-content" style="display: none;">

            <h2><abbr title="Linux kernel(s) to include in the system (you can choose multiple)">Kernel selection</abbr></h2>
            <table>
                <thead>
                    <tr><th>Kernel</th><th>Description</th><th>Select</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>linux</td>
                        <td>Stable mainline kernel (default)</td>
                        <td><input type="checkbox" name="kernel[linux]" checked></td>
                    </tr>
                    <tr>
                        <td>linux-lts</td>
                        <td>Long-term support kernel</td>
                        <td><input type="checkbox" name="kernel[linux-lts]"></td>
                    </tr>
                    <tr>
                        <td>linux-zen</td>
                        <td>Performance-focused kernel</td>
                        <td><input type="checkbox" name="kernel[linux-zen]"></td>
                    </tr>
                    <tr>
                        <td>linux-hardened</td>
                        <td>Security-focused kernel</td>
                        <td><input type="checkbox" name="kernel[linux-hardened]"></td>
                    </tr>
                </tbody>
            </table>

            <h2><abbr title="The system that manages services and startup">Init system</abbr></h2>
            <table>
                <thead>
                    <tr><th>Init system</th><th>Description</th><th>Select</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td>OpenRC</td>
                        <td>Lightweight, widely used (recommended)</td>
                        <td><input type="radio" name="init_system" value="openrc" checked></td>
                    </tr>
                    <tr>
                        <td>systemd</td>
                        <td>Default in many linux distributions, most support</td>
                        <td><input type="radio" name="init_system" value="systemd"></td>
                    </tr>
                    <tr>
                        <td>runit</td>
                        <td>Minimal, fastest, can be hard for new users</td>
                        <td><input type="radio" name="init_system" value="runit"></td>
                    </tr>
                    <tr>
                        <td>s6</td>
                        <td>Minimalist service manager</td>
                        <td><input type="radio" name="init_system" value="s6"></td>
                    </tr>
                    <tr>
                        <td>dinit</td>
                        <td>Modern, less common</td>
                        <td><input type="radio" name="init_system" value="dinit"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <br>
        <button type="submit">Build ISO</button>

    </form>
</main>

<script>
    const tableBody = document.querySelector('#package-table tbody');
    const searchInput = document.querySelector('#search');
    const prevBtn = document.querySelector('#prev-page');
    const nextBtn = document.querySelector('#next-page');
    const pageInfo = document.querySelector('#page-info');
    const additionalPackages = document.querySelector('#additional-packages tbody');
    const users = document.querySelector('#users tbody');

    let currentQuery = '';
    let page = 1;
    const perPage = 10;
    let total = 0;
    let totalPages = 1;

    function removeUser(el) {
        if (el.parentElement.parentElement.parentElement.children.length > 1) {
            el.closest('tr').remove();
            reindexUsers();
        } else {
            alert("At least one user must be specified");
        }
    }

    function reindexUsers() {
        const rows = users.rows;
        for (let i = 0; i < rows.length; i++) {
            const inputs = rows[i].querySelectorAll("input");

            inputs.forEach(input => {
                if (input.name.includes("[username]")) {
                    input.name = `users[${i}][username]`;
                } else if (input.name.includes("[password]")) {
                    input.name = `users[${i}][password]`;
                } else if (input.name.includes("[admin]")) {
                    input.name = `users[${i}][admin]`;
                }
            });
        }
    }

    function enforceRootRule(row) {
        const usernameInput = row.querySelector('input[type="text"]');
        const adminInput = row.querySelector('input[type="checkbox"]');

        if (usernameInput.value === "root") {
            adminInput.checked = true;
            adminInput.disabled = true;

            if (!adminInput.parentElement.querySelector("abbr")) {
                const abbr = document.createElement("abbr");
                abbr.title = "Root user must be an admin";

                adminInput.parentElement.replaceChild(abbr, adminInput);
                abbr.appendChild(adminInput);
            }
        } else {
            adminInput.disabled = false;

            const abbr = adminInput.parentElement;
            if (abbr.tagName === "ABBR") {
                const td = abbr.parentElement;
                td.replaceChild(adminInput, abbr);
            }
        }
    }

    function addUserRow() {
        const index = users.rows.length;
        const row = users.insertRow();
        
        row.innerHTML = `
            <td><input type="text" name="users[${index}][username]" value="" required></td>
            <td><input type="password" name="users[${index}][password]"></td>
            <td><input type="checkbox" name="users[${index}][admin]"></td>
            <td><button type="button" onclick="removeUser(this)">Remove</button></td>
        `;

        const usernameInput = row.querySelector('input[type="text"]');
        usernameInput.addEventListener("input", () => enforceRootRule(row));

        reindexUsers();
    }

    addUserRow();

    async function fetchPackages(query, page=1) {
        const url = <?php echo "`https://packages.$DOMAIN/api?q=\${encodeURIComponent(query)}&page=\${page}&per_page=\${perPage}`"; ?>;
        const res = await fetch(url);
        if (!res.ok) return { results: [], total: 0 };
        const data = await res.json();
        total = data.total || 0;
        totalPages = Math.max(1, Math.ceil(total / perPage));
        return data.results || [];
    }

    let packages = {};

    function updatePackages() {
        const names = Object.keys(packages);

        additionalPackages.innerHTML =
            names.length === 0
                ? "<tr><td>No additional packages selected</td></tr>"
                : names.map(pkg => `
                    <tr>
                        <td>${pkg}</td>
                        <td>
                            <input type="checkbox"
                                name="live_package[${pkg}]"
                                ${packages[pkg].live ? "checked" : ""}
                                onchange="togglePackage('${pkg}', 'live')">
                        </td>
                        <td>
                            <input type="checkbox"
                                name="system_package[${pkg}]"
                                ${packages[pkg].system ? "checked" : ""}
                                onchange="togglePackage('${pkg}', 'system')">
                        </td>
                    </tr>
                `).join('');
    }

    function removePackage(name) {
        delete packages[name];
        updatePackages();
    }

    function addPackage(name) {
        if (!packages[name]) {
            packages[name] = {
                live: false,
                system: true
            };
        }

        updatePackages();
    }

    function togglePackage(name, type) {
        if (!packages[name]) return;

        packages[name][type] = !packages[name][type];

        if (!packages[name].live && !packages[name].system) {
            delete packages[name];
        }

        updatePackages();
    }

    async function renderPackages(query) {
        const results = await fetchPackages(query, page);

        if (results.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="5">No packages found.</td></tr>';
        } else {
            tableBody.innerHTML = results.map(pkg => `
                <tr>
                    <td>${pkg.name}</td>
                    <td>${pkg.description}</td>
                    <td><button type="button" onclick="addPackage('${pkg.name}')">+</button>
                </tr>
            `).join('');
        }

        pageInfo.textContent = `Page ${page} of ${totalPages}`;
        prevBtn.disabled = page <= 1;
        nextBtn.disabled = page >= totalPages;
    }

    let debounceTimer = null;
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            currentQuery = searchInput.value.trim();
            page = 1;
            renderPackages(currentQuery);
        }, 300);
    });

    prevBtn.addEventListener('click', () => {
        if (page > 1) {
            page--;
            renderPackages(currentQuery);
        }
    });

    nextBtn.addEventListener('click', () => {
        if (page < totalPages) {
            page++;
            renderPackages(currentQuery);
        }
    });

    renderPackages('');

    const toggle = document.querySelector('.advanced-toggle');
    const content = document.querySelector('.advanced-content');
    toggle?.addEventListener('click', () => {
        if(content.style.display === 'block') {
            content.style.display = 'none';
            toggle.innerHTML = 'Advanced options &blacktriangledown;';
        } else {
            content.style.display = 'block';
            toggle.innerHTML = 'Advanced options &blacktriangle;';
        }
    });

    function validateForm() {
        const checkedKernels = document.querySelectorAll('input[name^="kernel"]:checked');
        
        if (checkedKernels.length === 0) {
            alert("You must select at least one kernel!");
            return false;
        }

        const admins = document.querySelectorAll('input[name$="[admin]"]:checked');

        if (admins.length === 0) {
            alert("You must select at least one administrative user!");
            return false;
        }

        return true;
    }
</script>

</body>
</html>
