// ========== TAB SWITCHING ==========
const tabs = document.querySelectorAll('.tab');
const tabContents = document.querySelectorAll('.tab-content');

tabs.forEach(tab => {
  tab.addEventListener('click', () => {
    tabs.forEach(t => t.classList.remove('active'));
    tabContents.forEach(content => content.classList.remove('active'));

    tab.classList.add('active');
    const targetTab = tab.getAttribute('data-tab');
    document.getElementById(targetTab).classList.add('active');

    clearAllErrors();
  });
});

// ========== STUDENT LOGIN ==========
document.getElementById('studentForm').addEventListener('submit', function(e) {
  e.preventDefault();

  const emailInput = document.getElementById('studentEmail');
  const passwordInput = document.getElementById('studentPassword');
  const remember = document.getElementById('rememberStudent').checked;
  const errorElement = document.getElementById('studentError');
  const submitBtn = e.target.querySelector('button[type="submit"]');

  let email = emailInput.value.trim();
  let password = passwordInput.value.trim();
  errorElement.textContent = '';

  if (!email) {
    errorElement.textContent = 'Please enter your email or roll number';
    return;
  }

  if (!password) {
    errorElement.textContent = 'Please enter your password';
    return;
  }

  submitBtn.disabled = true;
  submitBtn.textContent = 'Logging in...';

  fetch('/evnit/backend/api/auth/login.php',
 {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password, user_type: 'student' })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      if (remember) localStorage.setItem('rememberedUser', email);
      else localStorage.removeItem('rememberedUser');

      errorElement.style.color = '#4ade80';
      errorElement.textContent = 'Login successful! Redirecting...';

      setTimeout(() => {
        window.location.href = '../frontend/student-dashboard.html';
      }, 1000);
    } else {
      errorElement.style.color = 'red';
      errorElement.textContent = data.message || 'Invalid credentials';
      submitBtn.disabled = false;
      submitBtn.textContent = 'Login';
    }
  })
  .catch(error => {
    console.error('Error:', error);
    errorElement.textContent = 'Connection error. Check if XAMPP is running.';
    submitBtn.disabled = false;
    submitBtn.textContent = 'Login';
  });
});
// ========== DRIVER LOGIN ==========
document.getElementById('driverForm').addEventListener('submit', function(e) {
  e.preventDefault();

  const driverId = document.getElementById('driverId').value.trim();
  const password = document.getElementById('driverPassword').value.trim();
  const remember = document.getElementById('rememberDriver').checked;
  const errorElement = document.getElementById('driverError');
  const submitBtn = e.target.querySelector('button[type="submit"]');

  errorElement.textContent = '';

  if (!driverId) {
    errorElement.textContent = 'Please enter your Driver ID';
    return;
  }

  if (!password) {
    errorElement.textContent = 'Please enter your password';
    return;
  }

  submitBtn.disabled = true;
  submitBtn.textContent = 'Logging in...';

  // FIXED: Send 'login' instead of 'driverid' to match backend expectation
  fetch('/evnit/backend/api/auth/login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ 
  driver_id: driverId,   // ✅ correct key
  password: password, 
  user_type: 'driver' 
})

    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      if (remember) localStorage.setItem('rememberedDriver', driverId);
      else localStorage.removeItem('rememberedDriver');

      // Store driver data in localStorage
      localStorage.setItem('driver', JSON.stringify(data.user));

      errorElement.style.color = '#4ade80';
      errorElement.textContent = 'Login successful! Redirecting...';

      setTimeout(() => {
        window.location.href = '/evnit/frontend/driver-dashboard.html';
      }, 1000);
    } else {
      errorElement.style.color = 'red';
      errorElement.textContent = data.message || 'Invalid credentials';
      submitBtn.disabled = false;
      submitBtn.textContent = 'Login as Driver';
    }
  })
  .catch(error => {
    console.error('Error:', error);
    errorElement.textContent = 'Connection error. Check if XAMPP is running.';
    submitBtn.disabled = false;
    submitBtn.textContent = 'Login as Driver';
  });
});
// ========== ADMIN LOGIN ==========
let otpSent = false;
document.getElementById('adminForm').addEventListener('submit', function(e) {
  e.preventDefault();

  const username = document.getElementById('adminUsername').value.trim();
  const password = document.getElementById('adminPassword').value.trim();
  const otp = document.getElementById('adminOTP').value.trim();
  const remember = document.getElementById('rememberAdmin').checked;
  const errorElement = document.getElementById('adminError');
  const otpGroup = document.getElementById('otpGroup');
  const loginBtn = document.getElementById('adminLoginBtn');

  errorElement.textContent = '';

  if (!username) { errorElement.textContent = 'Please enter admin username'; return; }
  if (!password) { errorElement.textContent = 'Please enter password'; return; }

  if (!otpSent) {
    loginBtn.disabled = true;
    loginBtn.textContent = 'Sending OTP...';
    setTimeout(() => {
      otpSent = true;
      otpGroup.style.display = 'block';
      loginBtn.disabled = false;
      loginBtn.textContent = 'Verify & Login';
      alert('OTP sent to your registered email/phone!');
    }, 1500);
    return;
  }

  if (!otp) { errorElement.textContent = 'Please enter the OTP'; return; }
  if (otp.length !== 6) { errorElement.textContent = 'OTP must be 6 digits'; return; }

  loginBtn.disabled = true;
  loginBtn.textContent = 'Verifying...';
fetch('/evnit/backend/api/auth/login.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ login: username, password, user_type: 'admin' })

})
.then(response => {
    if (!response.ok) {
        throw new Error('Network response was not ok');
    }
    return response.json();
})
.then(data => {
    if (data.success) {
        if (remember) localStorage.setItem('rememberedAdmin', username);
        else localStorage.removeItem('rememberedAdmin');

        errorElement.style.color = '#4ade80';
        errorElement.textContent = 'Login successful! Redirecting...';

        // Absolute redirect path from localhost root
        setTimeout(() => {
            window.location.href = '/evnit/frontend/admin-dashboard.html';
        }, 1000);
    } else {
        errorElement.style.color = 'red';
        errorElement.textContent = data.message || 'Invalid credentials';
        loginBtn.disabled = false;
        loginBtn.textContent = 'Verify & Login';
    }
})
.catch(error => {
    console.error('Error:', error);
    errorElement.style.color = 'red';
    errorElement.textContent = 'Connection error. Check if XAMPP is running.';
    loginBtn.disabled = false;
    loginBtn.textContent = 'Verify & Login';
});

    
  })
  

// ========== GUEST LOGIN ==========
document.getElementById('guestBtn').addEventListener('click', function() {
  alert('Guest Mode - Limited Access\n\n✓ View campus map\n✓ Check live EV locations\n✗ Cannot book rides\n✗ No payment features');
  // window.location.href = '../frontend/guest-view.html';
});

// ========== FORGOT PASSWORD ==========
const forgotPasswordModal = document.getElementById('forgotPasswordModal');
['forgotPasswordStudent', 'forgotPasswordDriver', 'forgotPasswordAdmin'].forEach(linkId => {
  document.getElementById(linkId).addEventListener('click', e => {
    e.preventDefault();
    forgotPasswordModal.style.display = 'block';
  });
});

document.getElementById('sendResetLink').addEventListener('click', function() {
  const email = document.getElementById('resetEmail').value.trim();
  const errorElement = document.getElementById('resetError');
  const btn = this;
  errorElement.textContent = '';

  if (!email) { errorElement.textContent = 'Please enter your email address'; return; }
  const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailPattern.test(email)) { errorElement.textContent = 'Please enter a valid email address'; return; }

  btn.disabled = true;
  btn.textContent = 'Sending...';

  setTimeout(() => {
    alert('Password reset link sent to ' + email);
    forgotPasswordModal.style.display = 'none';
    document.getElementById('resetEmail').value = '';
    btn.disabled = false;
    btn.textContent = 'Send Reset Link';
  }, 1500);
});

// ========== REGISTRATION ==========
const registrationModal = document.getElementById('registrationModal');
document.getElementById('registerStudent').addEventListener('click', e => {
  e.preventDefault();
  registrationModal.style.display = 'block';
});

document.getElementById('registerBtn').addEventListener('click', function() {
  const name = document.getElementById('regName').value.trim();
  const email = document.getElementById('regEmail').value.trim();
  const password = document.getElementById('regPassword').value.trim();
  const phone = document.getElementById('regPhone').value.trim();
  const department = document.getElementById('regDepartment').value;
  const agreeTerms = document.getElementById('agreeTerms').checked;
  const errorElement = document.getElementById('regError');
  const btn = this;

  errorElement.textContent = '';
  if (!name) { errorElement.textContent = 'Please enter your full name'; return; }
  if (!email) { errorElement.textContent = 'Please enter your VNIT email'; return; }
  if (!password) { errorElement.textContent = 'Please create a password'; return; }
  if (password.length < 6) { errorElement.textContent = 'Password must be at least 6 characters'; return; }
  if (!phone || phone.length !== 10 || !/^\d+$/.test(phone)) { errorElement.textContent = 'Please enter a valid 10-digit phone number'; return; }
  if (!department) { errorElement.textContent = 'Please select your department'; return; }
  if (!agreeTerms) { errorElement.textContent = 'Please accept the Terms & Conditions'; return; }

  btn.disabled = true;
  btn.textContent = 'Registering...';

  fetch('/evnit/backend/api/auth/login.php',
{
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, email, password, phone, department })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      alert('Registration Successful!\n\nWelcome bonus of ₹100 added to your wallet.\n\nYou can now login.');
      registrationModal.style.display = 'none';

      document.getElementById('regName').value = '';
      document.getElementById('regEmail').value = '';
      document.getElementById('regPassword').value = '';
      document.getElementById('regPhone').value = '';
      document.getElementById('regDepartment').value = '';
      document.getElementById('agreeTerms').checked = false;

      btn.disabled = false;
      btn.textContent = 'Register';
    } else {
      errorElement.textContent = data.message || 'Registration failed';
      btn.disabled = false;
      btn.textContent = 'Register';
    }
  })
  .catch(error => {
    console.error('Error:', error);
    errorElement.textContent = 'Connection error. Please try again.';
    btn.disabled = false;
    btn.textContent = 'Register';
  });
});

// ========== MODAL CLOSE HANDLERS ==========
document.querySelectorAll('.close').forEach(btn => {
  btn.addEventListener('click', function() {
    this.closest('.modal').style.display = 'none';
  });
});

window.addEventListener('click', function(e) {
  if (e.target.classList.contains('modal')) {
    e.target.style.display = 'none';
  }
});

// ========== HELPER FUNCTIONS ==========
function clearAllErrors() {
  ['studentError', 'driverError', 'adminError', 'resetError', 'regError'].forEach(id => {
    document.getElementById(id).textContent = '';
  });
}

// ========== AUTO-FILL REMEMBERED USERS ==========
window.addEventListener('load', function() {
  const rememberedStudent = localStorage.getItem('rememberedUser');
  const rememberedDriver = localStorage.getItem('rememberedDriver');
  const rememberedAdmin = localStorage.getItem('rememberedAdmin');

  if (rememberedStudent) {
    document.getElementById('studentEmail').value = rememberedStudent;
    document.getElementById('rememberStudent').checked = true;
  }

  if (rememberedDriver) {
    document.getElementById('driverId').value = rememberedDriver;
    document.getElementById('rememberDriver').checked = true;
  }

  if (rememberedAdmin) {
    document.getElementById('adminUsername').value = rememberedAdmin;
    document.getElementById('rememberAdmin').checked = true;
  }
});
