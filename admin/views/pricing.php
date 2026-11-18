<?php
/**
 * Admin: simple pricing & profit calculator.
 * Everything is recalculated live in the browser; "Save" buttons store the
 * settings and model prices through the admin REST API.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'coachpro_admin' ) ) wp_die( 'Unauthorized' );

global $wpdb;
$t_models = CoachPro_DB::table( 'ai_models' );
$t_msg    = CoachPro_DB::table( 'messages' );

$models = array();
foreach ( (array) $wpdb->get_results( "SELECT id, display_name, api_model_name, provider, is_active, min_plan, api_input_usd_per_1m, api_output_usd_per_1m, input_credits_per_1k, output_credits_per_1k FROM `{$t_models}` WHERE category <> 'image' ORDER BY is_active DESC, output_credits_per_1k ASC", ARRAY_A ) as $m ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $models[] = array(
        'id' => $m['id'], 'name' => $m['display_name'], 'api' => $m['api_model_name'], 'provider' => $m['provider'],
        'active' => (int) $m['is_active'], 'plan' => $m['min_plan'],
        'usd_in' => (float) $m['api_input_usd_per_1m'], 'usd_out' => (float) $m['api_output_usd_per_1m'],
        'rate_in' => (float) $m['input_credits_per_1k'], 'rate_out' => (float) $m['output_credits_per_1k'],
    );
}

$usage = array();
foreach ( (array) $wpdb->get_results( "SELECT model_id, COUNT(*) AS replies, SUM(input_tokens) AS tin, SUM(output_tokens) AS tout, SUM(credits_used) AS credits FROM `{$t_msg}` WHERE role = 'assistant' AND created_at >= UTC_TIMESTAMP() - INTERVAL 30 DAY GROUP BY model_id", ARRAY_A ) as $u ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $usage[] = array( 'model' => (string) $u['model_id'], 'replies' => (int) $u['replies'], 'tin' => (int) $u['tin'], 'tout' => (int) $u['tout'], 'credits' => (float) $u['credits'] );
}

$data = array(
    'settings' => CoachPro_Pricing::settings(),
    'offers'   => CoachPro_Pricing::offers(),
    'models'   => $models,
    'usage'    => $usage,
    'rest'     => rest_url( 'coachpro/v1/admin/' ),
    'nonce'    => wp_create_nonce( 'wp_rest' ),
);
?>
<div class="wrap" id="cp-calc">
    <h1><?php esc_html_e( 'CoachPro AI — Pricing Calculator', 'coachpro-ai' ); ?></h1>
    <p class="description">Only administrators see this page. Users only ever see their own credit balance and how much each reply deducted.</p>

    <style>
        #cp-calc .card{max-width:none;padding:14px 18px;margin:14px 0}
        #cp-calc table.widefat td,#cp-calc table.widefat th{vertical-align:middle}
        #cp-calc input.small{width:90px}
        #cp-calc .good{color:#007017;font-weight:600}
        #cp-calc .bad{color:#b32d2e;font-weight:600}
        #cp-calc .big{font-size:15px}
        #cp-calc .formula{background:#f6f7f7;padding:8px 12px;border-left:4px solid #2271b1;margin:8px 0}
    </style>

    <div class="card">
        <h2>1. Basic settings</h2>
        <p>
            <label>US dollar rate (Rs) <input class="small" type="number" step="0.01" min="1" id="cp-usd"></label>
            &nbsp; <label>Target profit % <input class="small" type="number" step="1" min="0" max="95" id="cp-profit"></label>
            &nbsp; <label>Typical message: input tokens <input class="small" type="number" step="100" min="0" id="cp-tin"></label>
            &nbsp; <label>output tokens <input class="small" type="number" step="100" min="0" id="cp-tout"></label>
            &nbsp; <button class="button button-primary" id="cp-save-settings">Save settings</button>
        </p>
        <p class="description">Use the dollar rate your bank actually charges (include card/FX fees). A typical coding message is about 6,000 input tokens (instructions + chat history + pasted code) and 2,000 output tokens.</p>
        <div class="formula big" id="cp-summary"></div>
    </div>

    <div class="card">
        <h2>2. Plans &amp; credit packs — profit per credit</h2>
        <table class="widefat striped">
            <thead><tr><th>Name</th><th>Type</th><th>Credits</th><th>Price (Rs)</th><th>Price of 1 credit (Rs)</th><th>Max API cost of 1 credit (Rs)</th><th>Profit</th></tr></thead>
            <tbody id="cp-offers"></tbody>
        </table>
        <p class="description">Prices and credits are edited on the <a href="<?php echo esc_url( admin_url( 'admin.php?page=coachpro-plans' ) ); ?>">Plans &amp; Packs</a> page. The cheapest price of 1 credit decides the model prices, so every plan and pack earns at least the target profit.</p>
    </div>

    <div class="card">
        <h2>3. Models — API price → credits per 1,000 tokens</h2>
        <div class="formula">Credits per 1,000 tokens = (API $ per 1M tokens ÷ 1000) × dollar rate ÷ max API cost of 1 credit</div>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Model</th>
                    <th>API $ / 1M<br>input</th><th>API $ / 1M<br>output</th>
                    <th>Recommended credits / 1K<br>input · output</th>
                    <th>Current credits / 1K<br>input · output</th>
                    <th>Typical message:<br>API cost (Rs)</th>
                    <th>Typical message:<br>credits charged</th>
                    <th>Profit at<br>current prices</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="cp-models"></tbody>
        </table>
        <p><button class="button button-primary" id="cp-apply-all">Save API prices &amp; apply recommended credits to all models</button> <span id="cp-status" role="status"></span></p>
        <p class="description">API prices are the provider's list prices in US dollars per 1 million tokens (OpenAI, Anthropic and Google pricing pages). Check them whenever a provider changes its prices — for example Google doubles Gemini 3.8 Flash on 1 January 2027.</p>
    </div>

    <div class="card">
        <h2>4. Last 30 days — real usage</h2>
        <table class="widefat striped">
            <thead><tr><th>Model</th><th>Replies</th><th>Input tokens</th><th>Output tokens</th><th>API cost (Rs)</th><th>Credits charged</th><th>Credits worth (Rs)*</th><th>Profit (Rs)</th></tr></thead>
            <tbody id="cp-usage"></tbody>
        </table>
        <p class="description">* Credits valued at the cheapest price of 1 credit, so the real profit is equal or higher. Free signup credits (20 per new user) and background chat summaries are not charged to users; keep them in mind.</p>
    </div>
</div>
<script>
(function () {
    var D = <?php echo wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>;
    var $ = function (id) { return document.getElementById(id); };
    var S = D.settings;
    $('cp-usd').value = S.usd_rate; $('cp-profit').value = S.profit_pct; $('cp-tin').value = S.sample_input; $('cp-tout').value = S.sample_output;

    function num(v) { v = parseFloat(v); return isFinite(v) ? v : 0; }
    function rs(v) { return 'Rs ' + num(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function fx(v, d) { return num(v).toLocaleString(undefined, { maximumFractionDigits: d === undefined ? 4 : d }); }
    function esc(s) { var d = document.createElement('div'); d.textContent = String(s == null ? '' : s); return d.innerHTML; }
    function pct(p, target) { var c = p >= target - 0.05 ? 'good' : 'bad'; return '<span class="' + c + '">' + fx(p * 100, 1) + '%</span>'; }

    function settings() { return { usd: num($('cp-usd').value), profit: num($('cp-profit').value), tin: num($('cp-tin').value), tout: num($('cp-tout').value) }; }
    function cheapest() {
        var best = null;
        D.offers.forEach(function (o) { var p = o.price / o.credits; if (best === null || p < best.per) best = { per: p, name: o.name }; });
        return best || { per: 1.25, name: 'default' };
    }
    function costPerCredit(s) { return cheapest().per * (1 - s.profit / 100); }
    // Same rounding as the server: round up to 4 decimals.
    function perK(usdPerM, s, cpc) { if (usdPerM <= 0 || cpc <= 0) return 0; return Math.ceil((usdPerM / 1000 * s.usd) / cpc * 10000) / 10000; }

    function render() {
        var s = settings(), ch = cheapest(), cpc = costPerCredit(s), target = s.profit / 100;
        $('cp-summary').innerHTML = 'Cheapest price of 1 credit: <b>' + rs(ch.per) + '</b> (' + esc(ch.name) + ') → to keep ' + fx(s.profit, 1) + '% profit, the API may cost at most <b>' + rs(cpc) + '</b> per credit.';

        $('cp-offers').innerHTML = D.offers.length ? D.offers.map(function (o) {
            var per = o.price / o.credits;
            return '<tr><td>' + esc(o.name) + '</td><td>' + esc(o.type) + '</td><td>' + fx(o.credits, 0) + '</td><td>' + fx(o.price, 0) + '</td><td>' + rs(per) + '</td><td>' + rs(cpc) + '</td><td>' + pct(1 - cpc / per, target) + '</td></tr>';
        }).join('') : '<tr><td colspan="7">No paid plans or packs yet.</td></tr>';

        $('cp-models').innerHTML = D.models.map(function (m, i) {
            var rin = perK(m.usd_in, s, cpc), rout = perK(m.usd_out, s, cpc);
            var cost = (s.tin / 1000) * (m.usd_in / 1000 * s.usd) + (s.tout / 1000) * (m.usd_out / 1000 * s.usd);
            var credits = (s.tin / 1000) * m.rate_in + (s.tout / 1000) * m.rate_out;
            var paid = credits * ch.per;
            var profit = m.usd_in || m.usd_out ? (paid > 0 ? pct(1 - cost / paid, target) : '<span class="bad">free</span>') : '<em>enter API price</em>';
            return '<tr' + (m.active ? '' : ' style="opacity:.55"') + '>' +
                '<td><b>' + esc(m.name) + '</b><br><code>' + esc(m.api) + '</code> · ' + esc(m.plan) + (m.active ? '' : ' · inactive') + '</td>' +
                '<td><input class="small" type="number" step="0.01" min="0" data-i="' + i + '" data-k="usd_in" value="' + m.usd_in + '"></td>' +
                '<td><input class="small" type="number" step="0.01" min="0" data-i="' + i + '" data-k="usd_out" value="' + m.usd_out + '"></td>' +
                '<td>' + fx(rin) + ' · ' + fx(rout) + '</td>' +
                '<td>' + fx(m.rate_in) + ' · ' + fx(m.rate_out) + '</td>' +
                '<td>' + rs(cost) + '</td>' +
                '<td>' + fx(credits, 2) + ' (' + rs(paid) + ')</td>' +
                '<td>' + profit + '</td>' +
                '<td><button class="button" data-apply="' + i + '">Apply</button></td></tr>';
        }).join('');

        var byId = {}; D.models.forEach(function (m) { byId[m.id] = m; });
        var tot = { r: 0, i: 0, o: 0, c: 0, cr: 0, w: 0 };
        $('cp-usage').innerHTML = D.usage.length ? D.usage.map(function (u) {
            var m = byId[u.model] || { name: u.model || '(unknown)', usd_in: 0, usd_out: 0 };
            var cost = u.tin / 1e6 * m.usd_in * s.usd + u.tout / 1e6 * m.usd_out * s.usd;
            var worth = u.credits * ch.per;
            tot.r += u.replies; tot.i += u.tin; tot.o += u.tout; tot.c += cost; tot.cr += u.credits; tot.w += worth;
            return '<tr><td>' + esc(m.name) + '</td><td>' + fx(u.replies, 0) + '</td><td>' + fx(u.tin, 0) + '</td><td>' + fx(u.tout, 0) + '</td><td>' + rs(cost) + '</td><td>' + fx(u.credits, 2) + '</td><td>' + rs(worth) + '</td><td>' + (worth - cost >= 0 ? '<span class="good">' : '<span class="bad">') + rs(worth - cost) + '</span></td></tr>';
        }).join('') + '<tr><th>Total</th><th>' + fx(tot.r, 0) + '</th><th>' + fx(tot.i, 0) + '</th><th>' + fx(tot.o, 0) + '</th><th>' + rs(tot.c) + '</th><th>' + fx(tot.cr, 2) + '</th><th>' + rs(tot.w) + '</th><th>' + rs(tot.w - tot.c) + '</th></tr>'
            : '<tr><td colspan="8">No replies in the last 30 days.</td></tr>';
    }

    function call(path, method, body) {
        return fetch(D.rest + path, { method: method, credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': D.nonce }, body: JSON.stringify(body) })
            .then(function (r) { return r.json().catch(function () { return {}; }).then(function (d) { if (!r.ok) throw new Error(d.message || ('HTTP ' + r.status)); return d; }); });
    }
    function status(t) { $('cp-status').textContent = t; }
    function apply(i) {
        var s = settings(), cpc = costPerCredit(s), m = D.models[i];
        var body = { api_input_usd_per_1m: m.usd_in, api_output_usd_per_1m: m.usd_out, input_credits_per_1k: perK(m.usd_in, s, cpc), output_credits_per_1k: perK(m.usd_out, s, cpc) };
        if (!m.usd_in && !m.usd_out) return Promise.reject(new Error(m.name + ': enter the API price first.'));
        return call('models/' + encodeURIComponent(m.id), 'PUT', body).then(function (row) {
            m.rate_in = num(row.input_credits_per_1k); m.rate_out = num(row.output_credits_per_1k);
        });
    }

    // Settings update instantly; model prices update when the field is left (keeps typing smooth).
    document.getElementById('cp-calc').addEventListener('input', function (e) {
        if (!e.target.dataset || !e.target.dataset.k) render();
    });
    document.getElementById('cp-calc').addEventListener('change', function (e) {
        var t = e.target;
        if (t.dataset && t.dataset.k) { D.models[+t.dataset.i][t.dataset.k] = num(t.value); render(); }
    });
    document.getElementById('cp-calc').addEventListener('click', function (e) {
        var i = e.target.getAttribute && e.target.getAttribute('data-apply');
        if (i === null || i === undefined || i === '') return;
        e.target.disabled = true;
        apply(+i).then(function () { status('Saved: ' + D.models[+i].name); render(); }).catch(function (err) { status(err.message); e.target.disabled = false; });
    });
    $('cp-apply-all').addEventListener('click', function () {
        var btn = this; btn.disabled = true; status('Saving…');
        var chain = Promise.resolve(), saved = 0, skipped = [];
        D.models.forEach(function (m, i) {
            chain = chain.then(function () { if (!m.usd_in && !m.usd_out) { skipped.push(m.name); return; } return apply(i).then(function () { saved++; }); });
        });
        chain.then(function () { status('Saved ' + saved + ' model(s).' + (skipped.length ? ' Skipped (no API price): ' + skipped.join(', ') : '')); render(); })
            .catch(function (err) { status(err.message); }).finally(function () { btn.disabled = false; });
    });
    $('cp-save-settings').addEventListener('click', function () {
        var s = settings(), btn = this; btn.disabled = true;
        call('pricing-settings', 'POST', { usd_rate: s.usd, profit_pct: s.profit, sample_input: s.tin, sample_output: s.tout })
            .then(function () { status('Settings saved. Click "Apply" to update model prices.'); $('cp-summary').insertAdjacentHTML('beforeend', ' <span class="good">Saved.</span>'); })
            .catch(function (err) { alert(err.message); }).finally(function () { btn.disabled = false; });
    });
    render();
}());
</script>
