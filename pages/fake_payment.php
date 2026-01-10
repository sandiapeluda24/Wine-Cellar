<?php
session_start();
require_once __DIR__ . '/../includes/auth.php';
requireRole('coleccionista');

if (empty($_SESSION['cart'])) {
    header('Location: cart.php');
    exit;
}

include __DIR__ . '/../includes/header.php';
?>

<h1>Card payment</h1>

<?php if (!empty($_SESSION['pay_error'])): ?>
  <p style="color:red;"><?= htmlspecialchars($_SESSION['pay_error']) ?></p>
  <?php unset($_SESSION['pay_error']); ?>
<?php endif; ?>

<form method="post" action="payment_success_fake.php" id="payForm" style="max-width:420px;">
    <label>Cardholder name
        <input type="text" name="card_name" required>
    </label><br><br>

    <label>Card number
        <input type="text" name="card_number" inputmode="numeric" autocomplete="off"
               placeholder="4242 4242 4242 4242" required>
    </label><br><br>

    <div style="display:flex; gap:12px;">
        <label style="flex:1;">Expiry (MM/YY)
            <input type="text" name="expiry" placeholder="12/29" required>
        </label>
        <label style="flex:1;">CVC
            <input type="text" name="cvc" inputmode="numeric" placeholder="123" required>
        </label>
    </div>
    <br>

    <button type="submit" name="pay" value="1">Pay now</button>
    <a href="cart.php" style="margin-left:10px;">Cancel</a>
</form>

<script>
document.getElementById('payForm').addEventListener('submit', function(e) {
  const num = (this.card_number.value || '').replace(/\s+/g,'');
  const exp = (this.expiry.value || '').trim();
  const cvc = (this.cvc.value || '').trim();

  if (!/^\d{13,19}$/.test(num)) {
    alert('Please enter a valid card number (digits only).');
    e.preventDefault(); return;
  }
  if (!/^\d{2}\/\d{2}$/.test(exp)) {
    alert('Expiry must be in MM/YY format.');
    e.preventDefault(); return;
  }
  if (!/^\d{3,4}$/.test(cvc)) {
    alert('CVC must be 3 or 4 digits.');
    e.preventDefault(); return;
  }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
