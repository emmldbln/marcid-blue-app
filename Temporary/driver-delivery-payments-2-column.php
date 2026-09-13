<?php

date_default_timezone_set('Asia/Manila');
require_once '../auth/auth.php';
requireAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Driver's Delivery Payments - 2 Column Test</title>
<link rel="stylesheet" href="../assets/css/app.css">
<style>
/* =========================================================
   DRIVER DELIVERY PAYMENTS - 2 COLUMN TEST
   ========================================================= */
.driver-deliveries-card{margin-top:24px}
.driver-panel-section{margin-top:28px;padding-top:24px;border-top:1px solid var(--border)}
.driver-panel-header{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;margin-bottom:16px}
.driver-panel-title{color:var(--text);font-size:16px;font-weight:700;line-height:1.4}
.driver-panel-subtitle{margin-top:4px;color:var(--text-muted);font-size:13px;line-height:1.5}

/* =========================================================
   LEGEND / CONTROLS
   ========================================================= */
.driver-delivery-legend-copy{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-top:8px;color:var(--text-muted);font-size:11px;line-height:1.4}
.driver-delivery-legend-copy span{display:inline-flex;align-items:center;white-space:nowrap}
.driver-delivery-legend-copy .legend-label{color:var(--text);font-weight:700}
.delivery-status-item{font-weight:600}
.delivery-status-paid{color:var(--success)}
.delivery-status-due{color:var(--warning)}
.delivery-status-unpaid{color:var(--danger)}
.delivery-status-overpaid{color:var(--primary-dark)}
.driver-payment-controls{display:flex;flex-wrap:wrap;gap:8px}
.driver-payment-controls .btn{min-width:48px;height:38px;padding:0 12px}

/* =========================================================
   TWO CUSTOMERS PER ROW
   ========================================================= */
.driver-delivery-payment-rows{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));column-gap:24px;padding:0 18px;background:var(--background);border:1px solid var(--border);border-radius:var(--radius-md)}
.driver-delivery-entry{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(52px,.55fr) minmax(52px,.55fr) minmax(88px,.85fr) 52px 88px minmax(82px,.75fr) 32px;gap:7px;align-items:end;min-width:0;padding:13px 0}
.driver-delivery-entry:nth-child(n+3){border-top:1px solid var(--border)}
.driver-delivery-entry:nth-child(even){padding-left:18px;border-left:1px solid var(--border)}
.driver-delivery-entry .form-group{min-width:0;margin:0}
.driver-delivery-entry .form-input,.driver-delivery-entry .driver-delivery-balance{width:100%;height:36px;min-height:36px;box-sizing:border-box}

/* Smaller field labels so the compressed row stays clean. */
.driver-delivery-entry .form-label{font-size:11px;line-height:1.2;margin-bottom:4px;white-space:nowrap}

/* Price/Gal: compact enough for a normal two-digit price. */
.driver-delivery-entry .driver-delivery-price{width:52px;max-width:52px;padding:0 6px;font-size:12px}

/* Method: slightly wider again so the option text is comfortable. */
.driver-delivery-entry .driver-delivery-method{width:88px;max-width:88px;padding:0 16px 0 7px;font-size:11px}
.driver-delivery-entry select.form-input{line-height:normal}

/* Keep new numeric/text fields genuinely empty; placeholders are suggestions only. */
.driver-delivery-entry input{background-color:var(--surface)}

/* =========================================================
   BALANCE / REMOVE
   ========================================================= */
.driver-delivery-balance{width:82px!important;max-width:82px!important;padding:0 6px;display:flex;align-items:center;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--surface);font-size:10px;font-weight:700;line-height:1.2;white-space:nowrap;overflow:hidden}
.driver-delivery-balance-neutral{color:var(--text-muted);font-weight:500}
.driver-delivery-balance-paid{color:var(--success);background:var(--success-light);border-color:rgba(46,155,91,.18)}
.driver-delivery-balance-due{color:var(--warning);background:var(--warning-light);border-color:rgba(229,154,36,.18)}
.driver-delivery-balance-unpaid{color:var(--danger);background:var(--danger-light);border-color:rgba(217,83,79,.18)}
.driver-delivery-balance-overpaid{color:var(--primary-dark);background:var(--primary-light);border-color:rgba(22,135,201,.18)}
.driver-delivery-remove{width:32px;height:36px;min-width:32px;margin:0;padding:0;display:flex;align-items:center;justify-content:center;align-self:end;justify-self:center;box-sizing:border-box;border:1px solid var(--border);border-radius:var(--radius-sm);background:var(--surface);color:var(--danger);font-size:18px;font-weight:600;line-height:1;cursor:pointer}
.driver-delivery-remove:hover{background:var(--danger-light);border-color:var(--danger)}

/* =========================================================
   RESPONSIVE
   ========================================================= */
@media(max-width:1200px){
.driver-delivery-payment-rows{grid-template-columns:1fr;row-gap:0}
.driver-delivery-entry:nth-child(even){padding-left:0;border-left:0}
.driver-delivery-entry:nth-child(n+2){border-top:1px solid var(--border)}
}
@media(max-width:850px){
.driver-panel-header{flex-direction:column;align-items:stretch}
.driver-payment-controls{width:100%}
.driver-payment-controls .btn{flex:1}
.driver-delivery-entry{grid-template-columns:repeat(4,minmax(0,1fr))}
.driver-delivery-entry .driver-delivery-customer-group{grid-column:1/-1}
.driver-delivery-entry .driver-delivery-balance-group{grid-column:span 2}
.driver-delivery-remove{grid-column:4}
}
@media(max-width:550px){
.driver-delivery-entry{grid-template-columns:1fr 1fr}
.driver-delivery-entry .driver-delivery-customer-group,.driver-delivery-entry .driver-delivery-balance-group{grid-column:auto}
.driver-delivery-remove{grid-column:auto;justify-self:start}
}
</style>
</head>
<body>
<div class="app">
<aside class="sidebar">
<div class="sidebar-brand"><img src="../assets/images/mb-logo.png" alt="Marcid Blue Logo"></div>
<nav class="sidebar-nav">
<div class="nav-section-title">Main</div>
<a href="../pages/home.php" class="nav-item">🏠 <span>Home</span></a>
<a href="#" class="nav-item">👥 <span>Customers</span></a>
<a href="#" class="nav-item">📅 <span>Daily Records</span></a>
<a href="daily-closing.php" class="nav-item active">🧾 <span>Daily Closing</span></a>
<div class="nav-section-title" style="margin-top:25px">System</div>
<a href="#" class="nav-item">⚙️ <span>Settings</span></a>
<a href="#" class="nav-item">🚪 <span>Logout</span></a>
</nav>
</aside>
<main class="main"><section class="page-content">
<div class="page-header"><div><h1 class="page-title">Driver's Delivery Payments</h1><p class="page-description">Two-customer-per-row UI test based on the current Temporary Daily Closing design.</p></div></div>
<div class="card driver-deliveries-card"><div class="card-body"><div class="driver-panel-section" style="margin-top:0;padding-top:0;border-top:0">
<div class="driver-panel-header">
<div>
<div class="driver-panel-title">Driver's Delivery Payments</div>
<div class="driver-panel-subtitle">Same customer, quantity, Price/Gal, payment, method, and balance functionality as Shop Delivery Payments.</div>
<div class="driver-delivery-legend-copy" aria-label="Driver delivery payment status legend"><span class="legend-label">Status:</span><span class="delivery-status-item delivery-status-paid">● Paid</span><span class="delivery-status-item delivery-status-due">● Due</span><span class="delivery-status-item delivery-status-unpaid">● Unpaid</span><span class="delivery-status-item delivery-status-overpaid">● Overpaid</span></div>
</div>
<div class="driver-payment-controls" aria-label="Add or remove driver delivery payment rows">
<button type="button" class="btn btn-secondary" data-driver-payment-adjust="1">+1</button><button type="button" class="btn btn-secondary" data-driver-payment-adjust="2">+2</button><button type="button" class="btn btn-secondary" data-driver-payment-adjust="10">+10</button><button type="button" class="btn btn-secondary" data-driver-payment-adjust="-1">−1</button><button type="button" class="btn btn-secondary" data-driver-payment-adjust="-2">−2</button><button type="button" class="btn btn-secondary" data-driver-payment-adjust="-10">−10</button>
</div>
</div>
<div id="driverDeliveryPaymentRows" class="driver-delivery-payment-rows">
<div class="driver-delivery-entry">
<div class="form-group driver-delivery-customer-group"><label class="form-label">Customer</label><input type="text" class="form-input driver-delivery-customer" value="" placeholder="Select or enter customer" autocomplete="off"></div>
<div class="form-group"><label class="form-label">Slim</label><input type="number" class="form-input driver-delivery-slim" value="" min="0" step="1" placeholder="0"></div>
<div class="form-group"><label class="form-label">Round</label><input type="number" class="form-input driver-delivery-round" value="" min="0" step="1" placeholder="0"></div>
<div class="form-group"><label class="form-label">Payment</label><input type="number" class="form-input driver-delivery-payment" value="" min="0" step="0.01" placeholder="0"></div>
<div class="form-group"><label class="form-label">Price/Gal</label><input type="number" class="form-input driver-delivery-price" value="" min="0" max="99" step="5" placeholder="30" maxlength="2" inputmode="numeric"></div>
<div class="form-group"><label class="form-label">Method</label><select class="form-input driver-delivery-method"><option value="">Select</option><option value="Cash">Cash</option><option value="GCash">GCash</option><option value="Bank Transfer">Bank Transfer</option><option value="Other">Other</option></select></div>
<div class="form-group driver-delivery-balance-group"><label class="form-label">Balance</label><div class="driver-delivery-balance driver-delivery-balance-neutral">—</div></div>
<button type="button" class="driver-delivery-remove" title="Remove customer">×</button>
</div>
<div class="driver-delivery-entry">
<div class="form-group driver-delivery-customer-group"><label class="form-label">Customer</label><input type="text" class="form-input driver-delivery-customer" value="" placeholder="Select or enter customer" autocomplete="off"></div>
<div class="form-group"><label class="form-label">Slim</label><input type="number" class="form-input driver-delivery-slim" value="" min="0" step="1" placeholder="0"></div>
<div class="form-group"><label class="form-label">Round</label><input type="number" class="form-input driver-delivery-round" value="" min="0" step="1" placeholder="0"></div>
<div class="form-group"><label class="form-label">Payment</label><input type="number" class="form-input driver-delivery-payment" value="" min="0" step="0.01" placeholder="0"></div>
<div class="form-group"><label class="form-label">Price/Gal</label><input type="number" class="form-input driver-delivery-price" value="" min="0" max="99" step="5" placeholder="30" maxlength="2" inputmode="numeric"></div>
<div class="form-group"><label class="form-label">Method</label><select class="form-input driver-delivery-method"><option value="">Select</option><option value="Cash">Cash</option><option value="GCash">GCash</option><option value="Bank Transfer">Bank Transfer</option><option value="Other">Other</option></select></div>
<div class="form-group driver-delivery-balance-group"><label class="form-label">Balance</label><div class="driver-delivery-balance driver-delivery-balance-neutral">—</div></div>
<button type="button" class="driver-delivery-remove" title="Remove customer">×</button>
</div>
</div>
</div></div></div>
</section></main>
</div>
<script>
/* =========================================================
   DRIVER DELIVERY PAYMENT TEST LOGIC
   ========================================================= */
(function(){
'use strict';
const rowsContainer=document.getElementById('driverDeliveryPaymentRows');
if(!rowsContainer)return;

/* =========================================================
   CREATE EMPTY CUSTOMER ENTRY
   ========================================================= */
function createEntry(){
const entry=document.createElement('div');
entry.className='driver-delivery-entry';
entry.innerHTML=`
<div class="form-group driver-delivery-customer-group"><label class="form-label">Customer</label><input type="text" class="form-input driver-delivery-customer" value="" placeholder="Select or enter customer" autocomplete="off"></div>
<div class="form-group"><label class="form-label">Slim</label><input type="number" class="form-input driver-delivery-slim" value="" min="0" step="1" placeholder="0"></div>
<div class="form-group"><label class="form-label">Round</label><input type="number" class="form-input driver-delivery-round" value="" min="0" step="1" placeholder="0"></div>
<div class="form-group"><label class="form-label">Payment</label><input type="number" class="form-input driver-delivery-payment" value="" min="0" step="0.01" placeholder="0"></div>
<div class="form-group"><label class="form-label">Price/Gal</label><input type="number" class="form-input driver-delivery-price" value="" min="0" max="99" step="5" placeholder="30" maxlength="2" inputmode="numeric"></div>
<div class="form-group"><label class="form-label">Method</label><select class="form-input driver-delivery-method"><option value="">Select</option><option value="Cash">Cash</option><option value="GCash">GCash</option><option value="Bank Transfer">Bank Transfer</option><option value="Other">Other</option></select></div>
<div class="form-group driver-delivery-balance-group"><label class="form-label">Balance</label><div class="driver-delivery-balance driver-delivery-balance-neutral">—</div></div>
<button type="button" class="driver-delivery-remove" title="Remove customer">×</button>`;
return entry;
}

/* =========================================================
   BALANCE STATUS
   ========================================================= */
function updateBalance(entry){
const slim=parseFloat(entry.querySelector('.driver-delivery-slim')?.value)||0;
const round=parseFloat(entry.querySelector('.driver-delivery-round')?.value)||0;
const payment=parseFloat(entry.querySelector('.driver-delivery-payment')?.value)||0;
const price=parseFloat(entry.querySelector('.driver-delivery-price')?.value)||0;
const balance=entry.querySelector('.driver-delivery-balance');
if(!balance)return;
const gallons=slim+round;
const expected=gallons*price;
const difference=expected-payment;
balance.className='driver-delivery-balance';
if(gallons<=0||price<=0){balance.classList.add('driver-delivery-balance-neutral');balance.textContent='—';return}
if(payment<=0){balance.classList.add('driver-delivery-balance-unpaid');balance.textContent='Unpaid';return}
if(Math.abs(difference)<=0.005){balance.classList.add('driver-delivery-balance-paid');balance.textContent='Paid';return}
if(difference>0){balance.classList.add('driver-delivery-balance-due');balance.textContent=`Due ₱${difference.toFixed(2)}`;return}
balance.classList.add('driver-delivery-balance-overpaid');balance.textContent=`Over ₱${Math.abs(difference).toFixed(2)}`;
}
function refreshBalances(){rowsContainer.querySelectorAll('.driver-delivery-entry').forEach(updateBalance)}

/* =========================================================
   LIVE CALCULATION
   ========================================================= */
rowsContainer.addEventListener('input',e=>{const entry=e.target.closest('.driver-delivery-entry');if(entry)updateBalance(entry)});
rowsContainer.addEventListener('change',e=>{const entry=e.target.closest('.driver-delivery-entry');if(entry)updateBalance(entry)});

/* =========================================================
   +/- BUTTONS
   ========================================================= */
document.querySelectorAll('[data-driver-payment-adjust]').forEach(button=>{
button.addEventListener('click',()=>{
const amount=Number(button.dataset.driverPaymentAdjust);
if(amount>0){for(let i=0;i<amount;i++)rowsContainer.appendChild(createEntry());refreshBalances();return}
const removeCount=Math.min(Math.abs(amount),Math.max(0,rowsContainer.querySelectorAll('.driver-delivery-entry').length-1));
for(let i=0;i<removeCount;i++){const entries=rowsContainer.querySelectorAll('.driver-delivery-entry');entries[entries.length-1]?.remove()}
refreshBalances();
});
});

/* =========================================================
   INDIVIDUAL REMOVE BUTTON
   ========================================================= */
rowsContainer.addEventListener('click',e=>{
const button=e.target.closest('.driver-delivery-remove');
if(!button)return;
const entries=rowsContainer.querySelectorAll('.driver-delivery-entry');
if(entries.length<=1)return;
button.closest('.driver-delivery-entry')?.remove();
});

refreshBalances();
})();
</script>
</body>
</html>
