/* ==========================================================
   Budget Tracker — Frontend Logic
   Talks to the PHP API using fetch(). No frameworks.
   ========================================================== */

const API = 'api'; // relative path to the api/ folder

/* ---------- Small helpers ---------- */
async function apiCall(url, method = 'GET', body = null) {
    const opts = {
        method,
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include', // important: sends the PHP session cookie
    };
    if (body) opts.body = JSON.stringify(body);

    const res = await fetch(url, opts);
    const data = await res.json().catch(() => ({}));

    if (!res.ok) {
        throw new Error(data.error || `Request failed (${res.status})`);
    }
    return data;
}

function formatMoney(n) {
    const num = Number(n) || 0;
    return '₹' + num.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function todayStr() {
    return new Date().toISOString().split('T')[0];
}

/* ==========================================================
   AUTH PAGE LOGIC (login.html)
   ========================================================== */
function initAuthPage() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    const forms = document.querySelectorAll('.auth-form');
    const msgEl = document.getElementById('authMessage');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            tabBtns.forEach(b => b.classList.remove('active'));
            forms.forEach(f => f.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById(btn.dataset.tab + 'Form').classList.add('active');
            msgEl.textContent = '';
        });
    });

    function showMessage(text, isError = true) {
        msgEl.textContent = text;
        msgEl.className = 'message ' + (isError ? 'error' : 'success');
    }

    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = document.getElementById('loginUsername').value.trim();
        const password = document.getElementById('loginPassword').value;

        try {
            await apiCall(`${API}/auth.php?action=login`, 'POST', { username, password });
            window.location.href = 'index.html';
        } catch (err) {
            showMessage(err.message);
        }
    });

    document.getElementById('registerForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = document.getElementById('regUsername').value.trim();
        const email = document.getElementById('regEmail').value.trim();
        const password = document.getElementById('regPassword').value;

        try {
            await apiCall(`${API}/auth.php?action=register`, 'POST', { username, email, password });
            window.location.href = 'index.html';
        } catch (err) {
            showMessage(err.message);
        }
    });
}

/* ==========================================================
   MAIN APP PAGE LOGIC (index.html)
   ========================================================== */
function initAppPage() {
    let categories = [];
    let editingId = null;

    const els = {
        welcomeUser: document.getElementById('welcomeUser'),
        logoutBtn: document.getElementById('logoutBtn'),

        sumIncome: document.getElementById('sumIncome'),
        sumExpense: document.getElementById('sumExpense'),
        sumBalance: document.getElementById('sumBalance'),
        sumAllTime: document.getElementById('sumAllTime'),
        categoryBreakdown: document.getElementById('categoryBreakdown'),

        form: document.getElementById('transactionForm'),
        formTitle: document.getElementById('formTitle'),
        submitBtn: document.getElementById('submitBtn'),
        cancelEditBtn: document.getElementById('cancelEditBtn'),
        transactionId: document.getElementById('transactionId'),
        type: document.getElementById('type'),
        amount: document.getElementById('amount'),
        categorySelect: document.getElementById('category_id'),
        date: document.getElementById('transaction_date'),
        description: document.getElementById('description'),

        tableBody: document.getElementById('transactionsBody'),
        noTransactionsMsg: document.getElementById('noTransactionsMsg'),

        filterType: document.getElementById('filterType'),
        filterMonth: document.getElementById('filterMonth'),
        clearFilters: document.getElementById('clearFilters'),

        categoryForm: document.getElementById('categoryForm'),
        newCategoryName: document.getElementById('newCategoryName'),
        newCategoryType: document.getElementById('newCategoryType'),
        categoryList: document.getElementById('categoryList'),
    };

    els.date.value = todayStr();

    /* ---------- Auth check ---------- */
    async function checkAuth() {
        try {
            const data = await apiCall(`${API}/auth.php?action=me`);
            if (!data.loggedIn) {
                window.location.href = 'login.html';
                return false;
            }
            els.welcomeUser.textContent = `Hi, ${data.user.username}`;
            return true;
        } catch {
            window.location.href = 'login.html';
            return false;
        }
    }

    els.logoutBtn.addEventListener('click', async () => {
        await apiCall(`${API}/auth.php?action=logout`, 'POST');
        window.location.href = 'login.html';
    });

    /* ---------- Categories ---------- */
    async function loadCategories() {
        const data = await apiCall(`${API}/categories.php`);
        categories = data.categories;
        renderCategoryOptions();
        renderCategoryList();
    }

    function renderCategoryOptions() {
        const currentType = els.type.value;
        els.categorySelect.innerHTML = categories
            .filter(c => c.type === currentType)
            .map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`)
            .join('') || '<option value="">No categories</option>';
    }

    function renderCategoryList() {
        els.categoryList.innerHTML = categories.map(c => `
            <li>
                <span>${escapeHtml(c.name)} <small style="color:#9ca3af;">(${c.type})</small></span>
                <button class="delete-cat-btn" data-id="${c.id}">Remove</button>
            </li>
        `).join('');
    }

    els.type.addEventListener('change', renderCategoryOptions);

    els.categoryForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        try {
            await apiCall(`${API}/categories.php`, 'POST', {
                name: els.newCategoryName.value.trim(),
                type: els.newCategoryType.value,
            });
            els.newCategoryName.value = '';
            await loadCategories();
        } catch (err) {
            alert(err.message);
        }
    });

    els.categoryList.addEventListener('click', async (e) => {
        if (!e.target.classList.contains('delete-cat-btn')) return;
        const id = e.target.dataset.id;
        if (!confirm('Delete this category? Transactions using it will become Uncategorized.')) return;
        try {
            await apiCall(`${API}/categories.php?id=${id}`, 'DELETE');
            await loadCategories();
            await loadTransactions();
        } catch (err) {
            alert(err.message);
        }
    });

    /* ---------- Transactions: CRUD ---------- */
    async function loadTransactions() {
        const params = new URLSearchParams();
        if (els.filterType.value) params.set('type', els.filterType.value);
        if (els.filterMonth.value) params.set('month', els.filterMonth.value);

        const data = await apiCall(`${API}/transactions.php?${params.toString()}`);
        renderTransactions(data.transactions);
    }

    function renderTransactions(transactions) {
        if (!transactions.length) {
            els.tableBody.innerHTML = '';
            els.noTransactionsMsg.style.display = 'block';
            return;
        }
        els.noTransactionsMsg.style.display = 'none';

        els.tableBody.innerHTML = transactions.map(t => `
            <tr>
                <td>${t.transaction_date}</td>
                <td><span class="type-badge ${t.type}">${t.type}</span></td>
                <td>${escapeHtml(t.category_name || 'Uncategorized')}</td>
                <td>${escapeHtml(t.description || '—')}</td>
                <td class="${t.type === 'income' ? 'amount-income' : 'amount-expense'}">
                    ${t.type === 'income' ? '+' : '-'}${formatMoney(t.amount)}
                </td>
                <td class="row-actions">
                    <button class="edit-btn" data-id="${t.id}">Edit</button>
                    <button class="delete-btn" data-id="${t.id}">Delete</button>
                </td>
            </tr>
        `).join('');
    }

    els.tableBody.addEventListener('click', async (e) => {
        const id = e.target.dataset.id;
        if (!id) return;

        if (e.target.classList.contains('delete-btn')) {
            if (!confirm('Delete this transaction?')) return;
            try {
                await apiCall(`${API}/transactions.php?id=${id}`, 'DELETE');
                await loadTransactions();
                await loadSummary();
            } catch (err) {
                alert(err.message);
            }
        }

        if (e.target.classList.contains('edit-btn')) {
            try {
                const data = await apiCall(`${API}/transactions.php?id=${id}`);
                const t = data.transaction;
                editingId = t.id;
                els.transactionId.value = t.id;
                els.type.value = t.type;
                renderCategoryOptions();
                els.categorySelect.value = t.category_id || '';
                els.amount.value = t.amount;
                els.date.value = t.transaction_date;
                els.description.value = t.description || '';

                els.formTitle.textContent = 'Edit Transaction';
                els.submitBtn.textContent = 'Save Changes';
                els.cancelEditBtn.style.display = 'inline-block';
                els.form.scrollIntoView({ behavior: 'smooth' });
            } catch (err) {
                alert(err.message);
            }
        }
    });

    els.cancelEditBtn.addEventListener('click', resetForm);

    function resetForm() {
        editingId = null;
        els.form.reset();
        els.date.value = todayStr();
        els.transactionId.value = '';
        els.formTitle.textContent = 'Add Transaction';
        els.submitBtn.textContent = 'Add Transaction';
        els.cancelEditBtn.style.display = 'none';
        renderCategoryOptions();
    }

    els.form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const payload = {
            type: els.type.value,
            amount: parseFloat(els.amount.value),
            category_id: els.categorySelect.value || null,
            transaction_date: els.date.value,
            description: els.description.value.trim(),
        };

        try {
            if (editingId) {
                await apiCall(`${API}/transactions.php?id=${editingId}`, 'PUT', payload);
            } else {
                await apiCall(`${API}/transactions.php`, 'POST', payload);
            }
            resetForm();
            await loadTransactions();
            await loadSummary();
        } catch (err) {
            alert(err.message);
        }
    });

    /* ---------- Filters ---------- */
    els.filterType.addEventListener('change', loadTransactions);
    els.filterMonth.addEventListener('change', loadTransactions);
    els.clearFilters.addEventListener('click', () => {
        els.filterType.value = '';
        els.filterMonth.value = '';
        loadTransactions();
    });

    /* ---------- Summary / Dashboard ---------- */
    async function loadSummary() {
        const month = els.filterMonth.value || '';
        const data = await apiCall(`${API}/summary.php${month ? '?month=' + month : ''}`);

        els.sumIncome.textContent = formatMoney(data.monthly.income);
        els.sumExpense.textContent = formatMoney(data.monthly.expense);
        els.sumBalance.textContent = formatMoney(data.monthly.balance);
        els.sumAllTime.textContent = formatMoney(data.allTime.balance);

        renderBreakdown(data.categoryBreakdown);
    }

    function renderBreakdown(breakdown) {
        if (!breakdown.length) {
            els.categoryBreakdown.innerHTML = '<p class="empty-msg">No expenses yet this month.</p>';
            return;
        }
        const max = Math.max(...breakdown.map(b => parseFloat(b.total)));
        els.categoryBreakdown.innerHTML = breakdown.map(b => {
            const pct = max > 0 ? (parseFloat(b.total) / max) * 100 : 0;
            return `
                <div class="breakdown-row">
                    <span class="breakdown-name">${escapeHtml(b.category)}</span>
                    <div class="breakdown-bar-track">
                        <div class="breakdown-bar-fill" style="width:${pct}%"></div>
                    </div>
                    <span class="breakdown-amount">${formatMoney(b.total)}</span>
                </div>
            `;
        }).join('');
    }

    /* ---------- Init ---------- */
    (async function init() {
        const ok = await checkAuth();
        if (!ok) return;
        await loadCategories();
        await loadTransactions();
        await loadSummary();
    })();
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

/* ---------- Entry point: detect which page we're on ---------- */
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('loginForm')) {
        initAuthPage();
    } else if (document.getElementById('transactionForm')) {
        initAppPage();
    }
});
