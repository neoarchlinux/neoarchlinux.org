<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NeoArch ISO Builder</title>
    <link rel="stylesheet" href="/index.css">
    <style>
        main {
            padding: 2rem;
        }

        input {
            color: var(--fg-text);
            background-color: #181825;
            padding: 0.5rem;
            margin-bottom: 1rem;
            width: calc(100% - 20px);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 0.5rem;
            border: 1px solid #ccc;
            text-align: left;
        }

        th {
            background-color: #181825;
        }

        tr:nth-child(even) {
            background-color: #181825;
        }

        #pagination {
            margin-top: 1rem;
            text-align: center;
        }

        #pagination button {
            color: var(--fg-text);
            background-color: #181825;
            border: 1px solid #333;
            padding: 0.4rem 0.8rem;
            margin: 0 0.5rem;
            cursor: pointer;
            font-size: 0.9rem;
        }

        #pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        #pagination span {
            margin: 0 1rem;
            font-weight: bold;
        }
    </style>
</head>
<body>

<?php require_once '/var/www/src/header.php'; ?>

<main>
    <h1 style="text-align:center;">NeoArch ISO Builder</h1>

    <form method="POST" action="/build" class="iso-form" onsubmit="return validateForm()">

        <h2>Hostname</h2>
        <label>Hostname: <input type="text" name="hostname" value="neoarch" required></label><br>

        <h2>Language</h2>
        <label>Language: <input type="text" name="language" value="en_US.UTF-8"></label><br>

        <h2>Users</h2>
        <table id="users">
            <thead>
                <tr><th>Username</th><th>Password (optional)</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><input type="text" name="users[0][username]" required></td>
                    <td><input type="password" name="users[0][password]"></td>
                    <td><button type="button" onclick="this.closest('tr').remove()">Remove</button></td>
                </tr>
            </tbody>
        </table>
        <button type="button" onclick="addUserRow()">Add User</button><br>
        <small class="note">If no password is chosen now, you will be asked to choose it during the install.</small><br>

        <h2>Additional Packages</h2>
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
                <th>Remove</th>
            </tr>
            </thead>
            <tbody></tbody>
        </table>
        <br>

        <span class="advanced-toggle">Advanced options &blacktriangledown;</span>
        <div class="advanced-content" style="display: none;">

            <h2>Kernel Selection</h2>
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

            <h2>Init System</h2>
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

            <h2>Repositories</h2>
            <table>
                <tr>
                    <td>
                        Enable Testing repos (not recommended)
                    </td>
                    <td>
                        <input type="checkbox" name="enable_testing" id="enable_testing">
                    </td>
                </tr>
            </table>

            <h2>Parallel Downloads</h2>
            <label>Number of parallel downloads: <input type="number" name="parallel_downloads" value="5" min="1" max="32"></label><br>
            <small>Pick a number between 1 and 32, depending on your internet speed</small>

        </div>

        <br>
        <button type="submit">Build ISO</button>

    </form>
</main>

<script>
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

    function addUserRow() {
        const tbody = document.querySelector('#users tbody');
        const index = tbody.rows.length;
        const row = tbody.insertRow();
        row.innerHTML = `
            <td><input type="text" name="users[${index}][username]" value="neoarch${index}" required></td>
            <td><input type="password" name="users[${index}][password]"></td>
            <td><button type="button" onclick="this.closest('tr').remove()">Remove</button></td>
        `;
    }

    function validateForm() {
        const checkedKernels = document.querySelectorAll('input[name^="kernel"]:checked');
        if(checkedKernels.length === 0) {
            alert("You must select at least one kernel!");
            return false;
        }
        return true;
    }

    const tableBody = document.querySelector('#package-table tbody');
    const searchInput = document.querySelector('#search');
    const prevBtn = document.querySelector('#prev-page');
    const nextBtn = document.querySelector('#next-page');
    const pageInfo = document.querySelector('#page-info');
    const additionalPackages = document.querySelector('#additional-packages tbody');

    let currentQuery = '';
    let page = 1;
    const perPage = 10;
    let total = 0;
    let totalPages = 1;

    async function fetchPackages(query, page=1) {
        const url = `https://packages.neoarchlinux.org/api?q=${encodeURIComponent(query)}&page=${page}&per_page=${perPage}`;
        const res = await fetch(url);
        if (!res.ok) return { results: [], total: 0 };
        const data = await res.json();
        total = data.total || 0;
        totalPages = Math.max(1, Math.ceil(total / perPage));
        return data.results || [];
    }

    let packages = [];

    function updatePackages() {
        packages.sort();

        function onlyUnique(value, index, array) {
            return array.indexOf(value) === index;
        }

        packages = packages.filter(onlyUnique);

        additionalPackages.innerHTML = packages.map(pkg => `
            <tr>
                <td>${pkg}</td>
                <td><input type="checkbox" name="additional_package[${pkg}]" checked onchange="removePackage('${pkg}')"></td>
            </tr>
        `).join('');
    }

    function removePackage(name) {
        const index = packages.indexOf(name);
        
        if (index > -1) {
            packages.splice(index, 1);
        }

        updatePackages();
    }

    function addPackage(name) {
        packages.push(name);
        
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
</script>

</body>
</html>
