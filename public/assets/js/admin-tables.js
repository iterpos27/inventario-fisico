document.addEventListener('DOMContentLoaded', () => {
    if (!document.body.classList.contains('app-role-admin')) {
        return;
    }

    document.querySelectorAll('.table-responsive table').forEach((table, tableIndex) => {
        if (table.dataset.adminTable === 'off' || table.closest('.admin-table-wrap')) {
            return;
        }

        const tbody = table.tBodies[0];
        if (!tbody) {
            return;
        }

        const emptyRow = tbody.querySelector('td[colspan]');
        const allRows = Array.from(tbody.rows).filter((row) => !row.querySelector('td[colspan]'));
        if (allRows.length === 0) {
            table.classList.add('admin-data-table');
            return;
        }

        table.classList.add('admin-data-table');

        const responsive = table.closest('.table-responsive');
        const wrap = document.createElement('div');
        wrap.className = 'admin-table-wrap';
        responsive.parentNode.insertBefore(wrap, responsive);
        wrap.appendChild(responsive);

        const tableId = `admin-table-${tableIndex + 1}`;
        const lengths = [10, 25, 50, 100];
        let pageSize = lengths.includes(allRows.length) ? allRows.length : 10;
        let currentPage = 1;
        let searchTerm = '';
        let sortState = { index: -1, direction: 'asc' };

        const top = document.createElement('div');
        top.className = 'admin-table-top';
        top.innerHTML = `
            <label class="admin-table-length" for="${tableId}-length">
                <span>Mostrar</span>
                <select id="${tableId}-length" class="form-select form-select-sm">
                    ${lengths.map((length) => `<option value="${length}">${length}</option>`).join('')}
                </select>
                <span>registros</span>
            </label>
            <label class="admin-table-search" for="${tableId}-search">
                <span>Buscar:</span>
                <input id="${tableId}-search" class="form-control form-control-sm" type="search" autocomplete="off">
            </label>
        `;
        wrap.insertBefore(top, responsive);

        const bottom = document.createElement('div');
        bottom.className = 'admin-table-bottom';
        bottom.innerHTML = `
            <span class="admin-table-info"></span>
            <nav class="admin-table-pagination" aria-label="Paginacion de tabla"></nav>
        `;
        wrap.appendChild(bottom);

        const lengthSelect = top.querySelector('select');
        const searchInput = top.querySelector('input');
        const info = bottom.querySelector('.admin-table-info');
        const pagination = bottom.querySelector('.admin-table-pagination');

        const normalize = (value) => value
            .toString()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();

        const filteredRows = () => {
            const rows = searchTerm
                ? allRows.filter((row) => normalize(row.textContent).includes(normalize(searchTerm)))
                : [...allRows];

            if (sortState.index >= 0) {
                rows.sort((a, b) => {
                    const left = a.children[sortState.index]?.textContent.trim() ?? '';
                    const right = b.children[sortState.index]?.textContent.trim() ?? '';
                    const leftNumber = Number(left.replace(/[^\d.-]/g, ''));
                    const rightNumber = Number(right.replace(/[^\d.-]/g, ''));
                    const result = Number.isFinite(leftNumber) && Number.isFinite(rightNumber) && left !== '' && right !== ''
                        ? leftNumber - rightNumber
                        : left.localeCompare(right, 'es', { numeric: true, sensitivity: 'base' });

                    return sortState.direction === 'asc' ? result : -result;
                });
            }

            return rows;
        };

        const renderPaginationButton = (label, page, disabled = false, active = false) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `admin-page-btn${active ? ' is-active' : ''}`;
            button.textContent = label;
            button.disabled = disabled;
            button.addEventListener('click', () => {
                currentPage = page;
                render();
            });
            pagination.appendChild(button);
        };

        const render = () => {
            const rows = filteredRows();
            const total = rows.length;
            const totalPages = Math.max(1, Math.ceil(total / pageSize));
            currentPage = Math.min(currentPage, totalPages);
            const start = total === 0 ? 0 : (currentPage - 1) * pageSize + 1;
            const end = Math.min(currentPage * pageSize, total);
            const visibleRows = rows.slice(start - 1, end);

            allRows.forEach((row) => {
                row.hidden = true;
            });
            visibleRows.forEach((row) => {
                row.hidden = false;
                tbody.appendChild(row);
            });

            if (emptyRow) {
                emptyRow.closest('tr').hidden = total !== 0;
            }

            info.textContent = `Mostrando ${start} a ${end} de ${total} registros`;
            if (searchTerm && total !== allRows.length) {
                info.textContent += ` (filtrado de ${allRows.length})`;
            }

            pagination.innerHTML = '';
            renderPaginationButton('Anterior', Math.max(1, currentPage - 1), currentPage === 1);
            const windowStart = Math.max(1, currentPage - 2);
            const windowEnd = Math.min(totalPages, windowStart + 4);
            for (let page = windowStart; page <= windowEnd; page += 1) {
                renderPaginationButton(String(page), page, false, page === currentPage);
            }
            renderPaginationButton('Siguiente', Math.min(totalPages, currentPage + 1), currentPage === totalPages);
        };

        Array.from(table.tHead?.rows[0]?.cells ?? []).forEach((header, index) => {
            if (header.classList.contains('text-end') || header.textContent.trim().toLowerCase() === 'acciones') {
                return;
            }
            header.classList.add('is-sortable');
            header.tabIndex = 0;
            header.setAttribute('role', 'button');
            header.addEventListener('click', () => {
                sortState = {
                    index,
                    direction: sortState.index === index && sortState.direction === 'asc' ? 'desc' : 'asc',
                };
                Array.from(table.tHead.rows[0].cells).forEach((cell) => {
                    cell.removeAttribute('data-sort');
                });
                header.dataset.sort = sortState.direction;
                render();
            });
            header.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    header.click();
                }
            });
        });

        lengthSelect.value = String(pageSize);
        lengthSelect.addEventListener('change', () => {
            pageSize = Number(lengthSelect.value);
            currentPage = 1;
            render();
        });

        searchInput.addEventListener('input', () => {
            searchTerm = searchInput.value.trim();
            currentPage = 1;
            render();
        });

        render();
    });
});
