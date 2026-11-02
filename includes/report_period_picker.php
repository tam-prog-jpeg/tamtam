<?php
/**
 * includes/report_period_picker.php
 * -----------------------------------------------------------------
 * Shared "Report Period" control for admin/reports.php and
 * manager/reports.php. Lets the user generate a MONTHLY, QUARTERLY,
 * or ANNUAL report (or use a quick preset) in one click.
 *
 * It works by filling in the existing date_from / date_to fields of
 * the page's filter form (id="reportFilterForm") and submitting it —
 * so the on-screen report, the CSV export, and the printable PDF all
 * pick up the same period automatically, with no extra backend code.
 *
 * Requires: the page's filter form must have id="reportFilterForm".
 * -----------------------------------------------------------------
 */
$pickerYear  = (int) date('Y');
$pickerYears = range($pickerYear, $pickerYear - 5);
?>
<div class="card" style="margin-bottom:18px;">
    <div class="card-header"><h3>Report Period</h3></div>
    <div class="card-body">
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
            <span style="font-size:12px;color:var(--color-text-muted);align-self:center;margin-right:4px;">Quick presets:</span>
            <button type="button" class="btn btn-outline btn-sm" data-preset="this_month">This Month</button>
            <button type="button" class="btn btn-outline btn-sm" data-preset="last_month">Last Month</button>
            <button type="button" class="btn btn-outline btn-sm" data-preset="this_quarter">This Quarter</button>
            <button type="button" class="btn btn-outline btn-sm" data-preset="last_quarter">Last Quarter</button>
            <button type="button" class="btn btn-outline btn-sm" data-preset="this_year">This Year</button>
            <button type="button" class="btn btn-outline btn-sm" data-preset="last_year">Last Year</button>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:end;">
            <div class="form-group" style="margin-bottom:0;">
                <label>Report Type</label>
                <select id="rpType" class="form-control">
                    <option value="monthly">Monthly</option>
                    <option value="quarterly">Quarterly</option>
                    <option value="annual">Annual</option>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label>Year</label>
                <select id="rpYear" class="form-control">
                    <?php foreach ($pickerYears as $y): ?>
                        <option value="<?= $y ?>"><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;" id="rpMonthWrap">
                <label>Month</label>
                <select id="rpMonth" class="form-control">
                    <?php foreach (['January','February','March','April','May','June','July','August','September','October','November','December'] as $i => $mName): ?>
                        <option value="<?= $i ?>" <?= $i === (int) date('n') - 1 ? 'selected' : '' ?>><?= $mName ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;display:none;" id="rpQuarterWrap">
                <label>Quarter</label>
                <select id="rpQuarter" class="form-control">
                    <option value="0">Q1 (Jan–Mar)</option>
                    <option value="1">Q2 (Apr–Jun)</option>
                    <option value="2">Q3 (Jul–Sep)</option>
                    <option value="3">Q4 (Oct–Dec)</option>
                </select>
            </div>
            <button type="button" class="btn btn-primary btn-sm" id="rpGenerate">Generate Report</button>
        </div>
        <div class="form-hint" style="margin-top:10px;">This fills in the From/To dates below. The Excel (CSV) and Print/PDF exports will use the same period.</div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('reportFilterForm');
    if (!form) return;

    // Format a Date as YYYY-MM-DD using LOCAL parts (toISOString would shift the day by timezone).
    const fmt = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');

    function applyRange(from, to) {
        form.querySelector('[name="date_from"]').value = fmt(from);
        form.querySelector('[name="date_to"]').value = fmt(to);
        form.submit();
    }

    const quarterRange = (year, q) => [new Date(year, q * 3, 1), new Date(year, q * 3 + 3, 0)];

    // ---- Quick presets ----
    document.querySelectorAll('[data-preset]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const now = new Date(), y = now.getFullYear(), m = now.getMonth();
            switch (btn.dataset.preset) {
                case 'this_month':   return applyRange(new Date(y, m, 1), new Date(y, m + 1, 0));
                case 'last_month':   return applyRange(new Date(y, m - 1, 1), new Date(y, m, 0));
                case 'this_quarter': return applyRange(...quarterRange(y, Math.floor(m / 3)));
                case 'last_quarter': {
                    let q = Math.floor(m / 3) - 1, yy = y;
                    if (q < 0) { q = 3; yy = y - 1; }
                    return applyRange(...quarterRange(yy, q));
                }
                case 'this_year':    return applyRange(new Date(y, 0, 1), new Date(y, 11, 31));
                case 'last_year':    return applyRange(new Date(y - 1, 0, 1), new Date(y - 1, 11, 31));
            }
        });
    });

    // ---- Specific monthly / quarterly / annual picker ----
    const typeSel = document.getElementById('rpType');
    const monthWrap = document.getElementById('rpMonthWrap');
    const quarterWrap = document.getElementById('rpQuarterWrap');

    function syncVisibility() {
        monthWrap.style.display = typeSel.value === 'monthly' ? '' : 'none';
        quarterWrap.style.display = typeSel.value === 'quarterly' ? '' : 'none';
    }
    typeSel.addEventListener('change', syncVisibility);
    syncVisibility();

    document.getElementById('rpGenerate').addEventListener('click', () => {
        const year = parseInt(document.getElementById('rpYear').value, 10);
        if (typeSel.value === 'monthly') {
            const month = parseInt(document.getElementById('rpMonth').value, 10);
            applyRange(new Date(year, month, 1), new Date(year, month + 1, 0));
        } else if (typeSel.value === 'quarterly') {
            applyRange(...quarterRange(year, parseInt(document.getElementById('rpQuarter').value, 10)));
        } else {
            applyRange(new Date(year, 0, 1), new Date(year, 11, 31));
        }
    });
})();
</script>
