<?php
/**
 * Database Inspector View
 */
?>

<div class="md3-card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h2 class="md3-card-title">Database Inspector</h2>
            <p class="md3-card-subtitle">Browse and query your WordPress database</p>
        </div>
        <div>
            <button class="md3-button md3-button-outlined" onclick="loadTables()">
                <span class="material-symbols-outlined"
                    style="margin-right: 8px; vertical-align: middle;">refresh</span>
                Refresh Tables
            </button>
        </div>
    </div>

    <div style="display: flex; gap: 24px;">
        <!-- Table List -->
        <div style="flex: 0 0 250px; border-right: 1px solid var(--md-sys-color-outline-variant); padding-right: 16px;">
            <h3 class="md3-title-medium" style="margin-bottom: 16px;">Tables</h3>
            <div id="table-list" style="max-height: 70vh; overflow-y: auto;">
                <div style="display: flex; justify-content: center; padding: 24px;">
                    <div class="md3-circular-progress"></div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div style="flex: 1; overflow: hidden; display: flex; flex-direction: column;">
            <div id="table-view-header"
                style="display: none; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 class="md3-title-medium" id="current-table-name"></h3>
                <div style="display: flex; gap: 8px;">
                    <button class="md3-button md3-button-text" onclick="showTableStructure()">Structure</button>
                    <button class="md3-button md3-button-filled" onclick="showTableData()">Browse</button>
                </div>
            </div>

            <div id="inspector-content" style="flex: 1; overflow: auto; position: relative;">
                <div style="text-align: center; padding: 48px; color: var(--md-sys-color-on-surface-variant);">
                    <span class="material-symbols-outlined"
                        style="font-size: 48px; margin-bottom: 16px; display: block;">dataset</span>
                    <p>Select a table to inspect or browse data</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let currentTable = null;
    let currentLimit = 50;
    let currentOffset = 0;

    document.addEventListener('DOMContentLoaded', () => {
        loadTables();
    });

    function loadTables() {
        const list = document.getElementById('table-list');
        list.innerHTML = '<div style="display: flex; justify-content: center; padding: 24px;"><div class="md3-circular-progress"></div></div>';

        WPSafeMode.API.get('/api/database?action=tables')
            .then(response => {
                if (response.success) {
                    renderTableList(response.data.tables);
                } else {
                    list.innerHTML = `<div class="md3-banner error">${response.message}</div>`;
                }
            })
            .catch(error => {
                list.innerHTML = `<div class="md3-banner error">Error loading tables: ${error.message}</div>`;
            });
    }

    function renderTableList(tables) {
        const list = document.getElementById('table-list');
        list.innerHTML = '';

        const ul = document.createElement('ul');
        ul.style.listStyle = 'none';
        ul.style.padding = '0';
        ul.style.margin = '0';

        tables.forEach(table => {
            const li = document.createElement('li');
            li.style.marginBottom = '2px';

            const btn = document.createElement('button');
            btn.className = 'md3-button md3-button-text';
            btn.style.width = '100%';
            btn.style.justifyContent = 'flex-start';
            btn.style.textAlign = 'left';
            btn.style.whiteSpace = 'nowrap';
            btn.style.overflow = 'hidden';
            btn.style.textOverflow = 'ellipsis';
            btn.innerHTML = `<span class="material-symbols-outlined" style="font-size: 18px; margin-right: 8px;">table_chart</span> ${table.Name}`;
            btn.title = `${table.Name} (${table.Rows} rows)`;
            btn.onclick = () => selectTable(table.Name);

            li.appendChild(btn);
            ul.appendChild(li);
        });

        list.appendChild(ul);
    }

    function selectTable(tableName) {
        currentTable = tableName;
        currentOffset = 0;
        document.getElementById('current-table-name').textContent = tableName;
        document.getElementById('table-view-header').style.display = 'flex';

        // Highlight active
        const btns = document.querySelectorAll('#table-list button');
        btns.forEach(b => {
            if (b.textContent.trim().endsWith(tableName)) {
                b.classList.add('md3-button-tonal');
                b.classList.remove('md3-button-text');
            } else {
                b.classList.remove('md3-button-tonal');
                b.classList.add('md3-button-text');
            }
        });

        showTableData();
    }

    function showTableData() {
        if (!currentTable) return;

        const container = document.getElementById('inspector-content');
        container.innerHTML = '<div style="display: flex; justify-content: center; padding: 48px;"><div class="md3-circular-progress"></div></div>';

        WPSafeMode.API.get('/api/database', {
            action: 'data',
            table: currentTable,
            limit: currentLimit,
            offset: currentOffset
        })
            .then(response => {
                if (response.success) {
                    renderTableData(response.data);
                } else {
                    container.innerHTML = `<div class="md3-banner error">${response.message}</div>`;
                }
            })
            .catch(error => {
                container.innerHTML = `<div class="md3-banner error">Error: ${error.message}</div>`;
            });
    }

    function renderTableData(data) {
        const container = document.getElementById('inspector-content');

        if (!data.rows || data.rows.length === 0) {
            container.innerHTML = '<div style="text-align: center; padding: 48px; color: var(--md-sys-color-on-surface-variant);">Table is empty</div>';
            return;
        }

        const columns = Object.keys(data.rows[0]);

        let html = `
            <div class="md3-table-container">
                <table class="md3-table">
                    <thead>
                        <tr>`;

        columns.forEach(col => {
            html += `<th>${col}</th>`;
        });

        html += `       </tr>
                    </thead>
                    <tbody>`;

        data.rows.forEach(row => {
            html += `<tr>`;
            columns.forEach(col => {
                let val = row[col];
                if (val === null) val = '<em style="color: grey;">NULL</em>';
                else if (val.length > 50) val = val.substring(0, 50) + '...';
                html += `<td>${WPSafeMode.Utils.escapeHtml(String(val))}</td>`;
            });
            html += `</tr>`;
        });

        html += `   </tbody>
                </table>
            </div>
            
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 16px;">
                <span>Showing ${data.offset + 1}-${Math.min(data.offset + data.limit, data.total)} of ${data.total}</span>
                <div style="display: flex; gap: 8px;">
                    <button class="md3-button md3-button-outlined" ${data.offset === 0 ? 'disabled' : ''} onclick="prevPage()">Previous</button>
                    <button class="md3-button md3-button-outlined" ${data.offset + data.limit >= data.total ? 'disabled' : ''} onclick="nextPage()">Next</button>
                </div>
            </div>`;

        container.innerHTML = html;
    }

    function showTableStructure() {
        if (!currentTable) return;

        const container = document.getElementById('inspector-content');
        container.innerHTML = '<div style="display: flex; justify-content: center; padding: 48px;"><div class="md3-circular-progress"></div></div>';

        WPSafeMode.API.get('/api/database', {
            action: 'schema',
            table: currentTable
        })
            .then(response => {
                if (response.success) {
                    renderTableSchema(response.data);
                } else {
                    container.innerHTML = `<div class="md3-banner error">${response.message}</div>`;
                }
            })
            .catch(error => {
                container.innerHTML = `<div class="md3-banner error">Error: ${error.message}</div>`;
            });
    }

    function renderTableSchema(data) {
        const container = document.getElementById('inspector-content');

        let html = `
            <h4 class="md3-title-small" style="margin: 16px 0 8px;">Columns</h4>
            <div class="md3-table-container" style="margin-bottom: 24px;">
                <table class="md3-table">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Type</th>
                            <th>Null</th>
                            <th>Key</th>
                            <th>Default</th>
                            <th>Extra</th>
                        </tr>
                    </thead>
                    <tbody>`;

        data.columns.forEach(col => {
            html += `<tr>
                <td>${col.Field}</td>
                <td>${col.Type}</td>
                <td>${col.Null}</td>
                <td>${col.Key}</td>
                <td>${col.Default}</td>
                <td>${col.Extra}</td>
            </tr>`;
        });

        html += `   </tbody>
                </table>
            </div>`;

        if (data.indexes && data.indexes.length > 0) {
            html += `
            <h4 class="md3-title-small" style="margin: 16px 0 8px;">Indexes</h4>
            <div class="md3-table-container">
                <table class="md3-table">
                    <thead>
                        <tr>
                            <th>Key Name</th>
                            <th>Column</th>
                            <th>Non_unique</th>
                            <th>Seq_in_index</th>
                        </tr>
                    </thead>
                    <tbody>`;

            data.indexes.forEach(idx => {
                html += `<tr>
                    <td>${idx.Key_name}</td>
                    <td>${idx.Column_name}</td>
                    <td>${idx.Non_unique}</td>
                    <td>${idx.Seq_in_index}</td>
                </tr>`;
            });

            html += `   </tbody>
                </table>
            </div>`;
        }

        container.innerHTML = html;
    }

    function prevPage() {
        if (currentOffset >= currentLimit) {
            currentOffset -= currentLimit;
            showTableData();
        }
    }

    function nextPage() {
        currentOffset += currentLimit;
        showTableData();
    }
</script>