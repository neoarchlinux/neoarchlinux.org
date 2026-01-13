<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>NeoArch Linux Packages</title>
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
    <input type="text" id="search" placeholder="Search packages by name or description..." />

    <table id="package-table">
    <thead>
    <tr>
        <th>Name</th>
        <th>Version</th>
        <th>Arch</th>
        <th>Repo</th>
        <th>Description</th>
    </tr>
    </thead>
    <tbody>
    <tr><td colspan="5">Loading packages…</td></tr>
    </tbody>
    </table>

    <div id="pagination">
        <button id="prev-page" disabled>&#9664;&nbsp;&nbsp;Previous</button>
        <span id="page-info">Page 1</span>
        <button id="next-page" disabled>Next&nbsp;&nbsp;&#9654;</button>
    </div>
</main>

<script>
    const tableBody = document.querySelector('#package-table tbody');
    const searchInput = document.querySelector('#search');
    const prevBtn = document.querySelector('#prev-page');
    const nextBtn = document.querySelector('#next-page');
    const pageInfo = document.querySelector('#page-info');

    let currentQuery = '';
    let page = 1;
    const perPage = 50;
    let total = 0;
    let totalPages = 1;

    async function fetchPackages(query, page=1) {
        const url = `/api?q=${encodeURIComponent(query)}&page=${page}&per_page=${perPage}`;
        const res = await fetch(url);
        if (!res.ok) return { results: [], total: 0 };
        const data = await res.json();
        total = data.total || 0;
        totalPages = Math.max(1, Math.ceil(total / perPage));
        return data.results || [];
    }

    async function renderPackages(query) {
        tableBody.innerHTML = '<tr><td colspan="5">Loading packages…</td></tr>';
        const results = await fetchPackages(query, page);

        if (results.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="5">No packages found.</td></tr>';
        } else {
            tableBody.innerHTML = results.map(pkg => `
                <tr>
                    <td>${pkg.name}</td>
                    <td>${pkg.version}</td>
                    <td>${pkg.arch}</td>
                    <td>${pkg.repo}</td>
                    <td>${pkg.description}</td>
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
