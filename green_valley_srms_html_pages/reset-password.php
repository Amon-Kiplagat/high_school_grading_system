<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password - Green Valley Secondary School</title>
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- Google Fonts: Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          fontFamily: {
            sans: ['Inter', 'sans-serif'],
          },
          colors: {
            brand: {
              50: '#f0f6ff',
              100: '#e0edff',
              500: '#3b82f6',
              600: '#2563eb',
              700: '#1d4ed8',
            }
          },
          animation: {
            'fade-in': 'fadeIn 0.3s ease-out forwards',
            'slide-up': 'slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards',
            'pulse-subtle': 'pulseSubtle 2s infinite',
          },
          keyframes: {
            fadeIn: {
              '0%': { opacity: '0' },
              '100%': { opacity: '1' },
            },
            slideUp: {
              '0%': { opacity: '0', transform: 'translateY(12px)' },
              '100%': { opacity: '1', transform: 'translateY(0)' },
            },
            pulseSubtle: {
              '0%, 100%': { opacity: '1' },
              '50%': { opacity: '0.85' },
            }
          }
        }
      }
    }
  </script>
  <style>
    /* Smooth transitions for interactive elements */
    .view-card {
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .custom-ring:focus-within {
      box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.5), 0 10px 25px -5px rgba(59, 130, 246, 0.1);
    }
  </style>
</head>
<body class="h-full flex flex-col items-center justify-center p-4 relative overflow-x-hidden font-sans antialiased bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-slate-900 via-slate-950 to-black">

  <!-- Ambient background glow elements -->
  <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>
  <div class="absolute bottom-1/4 right-1/4 w-[350px] h-[350px] bg-indigo-600/10 rounded-full blur-3xl pointer-events-none"></div>

  <!-- Header Navigation / Dev Mode Toggle -->
  <header class="absolute top-6 left-0 right-0 px-6 max-w-6xl mx-auto flex justify-between items-center z-10">
    <div class="flex items-center space-x-3">
      <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center shadow-lg shadow-blue-500/20">
        <i class="fa-solid fa-shield-halved text-white text-lg"></i>
      </div>
      <span class="font-bold text-lg tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-white to-slate-400">Green Valley</span>
    </div>

    <!-- Quick Step Selector for Demo Purposes -->
    <div class="bg-slate-900/80 backdrop-blur-md border border-slate-800 rounded-full p-1 flex items-center text-xs text-slate-400 shadow-inner">
      <button onclick="switchStep(1)" id="step-btn-1" class="px-3 py-1.5 rounded-full transition-all duration-200 text-white bg-blue-600 font-medium shadow-sm">1. Request</button>
      <button onclick="switchStep(2)" id="step-btn-2" class="px-3 py-1.5 rounded-full transition-all duration-200 hover:text-white">2. Create New</button>
      <button onclick="switchStep(3)" id="step-btn-3" class="px-3 py-1.5 rounded-full transition-all duration-200 hover:text-white">3. Success</button>
    </div>
  </header>

  <!-- Main Container -->
  <main class="w-full max-w-md relative z-10 my-auto">
    <!-- Card Glassmorphism -->
    <div class="bg-slate-900/70 backdrop-blur-xl border border-slate-800/80 rounded-2xl shadow-2xl shadow-black/50 p-6 sm:p-8 view-card">

      <!-- UI prototype: connect the forms to secure PHP/PostgreSQL reset-token endpoints before production use. -->
      <!-- STEP 1: REQUEST PASSWORD RESET -->
      <section id="step-1" class="space-y-6 animate-fade-in">
        <div class="text-center space-y-2">
          <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-blue-500/10 border border-blue-500/20 text-blue-400 mb-2">
            <i class="fa-solid fa-key text-xl"></i>
          </div>
          <h1 class="text-2xl font-bold tracking-tight text-white">Reset your password</h1>
          <p class="text-sm text-slate-400">Enter your registered email address and we'll send you a link to reset your password.</p>
        </div>

        <form id="request-form" onsubmit="handleRequestSubmit(event)" class="space-y-4">
          <div class="space-y-1.5">
            <label for="request-email" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Email Address</label>
            <div class="relative rounded-xl custom-ring transition-all">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                <i class="fa-regular fa-envelope"></i>
              </div>
              <input type="email" id="request-email" required placeholder="name@company.com" 
                class="w-full pl-10 pr-4 py-3 bg-slate-950/60 border border-slate-800 rounded-xl text-slate-100 placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm transition-colors">
            </div>
            <p id="email-error" class="hidden text-xs text-rose-400 mt-1 flex items-center gap-1">
              <i class="fa-solid fa-circle-exclamation"></i> Please enter a valid email address.
            </p>
          </div>

          <button type="submit" id="request-submit-btn" class="w-full py-3.5 px-4 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-medium rounded-xl text-sm transition-all duration-200 shadow-lg shadow-blue-600/25 active:scale-[0.99] flex items-center justify-center space-x-2 group">
            <span>Send Reset Link</span>
            <i class="fa-solid fa-arrow-right text-xs group-hover:translate-x-0.5 transition-transform"></i>
          </button>
        </form>

        <div class="pt-4 border-t border-slate-800/60 text-center">
          <a href="login.html" class="inline-flex items-center text-xs font-medium text-slate-400 hover:text-slate-200 transition-colors gap-2">
            <i class="fa-solid fa-arrow-left text-xs"></i>
            <span>Back to Login</span>
          </a>
        </div>
      </section>


      <!-- STEP 2: CREATE NEW PASSWORD -->
      <section id="step-2" class="hidden space-y-6 animate-fade-in">
        <div class="text-center space-y-2">
          <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-blue-500/10 border border-blue-500/20 text-blue-400 mb-2">
            <i class="fa-solid fa-lock text-xl"></i>
          </div>
          <h1 class="text-2xl font-bold tracking-tight text-white">Set new password</h1>
          <p class="text-sm text-slate-400">Your new password must be different from previously used passwords.</p>
        </div>

        <form id="reset-form" onsubmit="handleResetSubmit(event)" class="space-y-5">
          <!-- Password Field -->
          <div class="space-y-1.5">
            <label for="new-password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">New Password</label>
            <div class="relative rounded-xl custom-ring transition-all">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                <i class="fa-solid fa-lock-open"></i>
              </div>
              <input type="password" id="new-password" required placeholder="••••••••••••" 
                oninput="validatePassword()"
                class="w-full pl-10 pr-10 py-3 bg-slate-950/60 border border-slate-800 rounded-xl text-slate-100 placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm transition-colors">
              <button type="button" onclick="togglePasswordVisibility('new-password', 'toggle-icon-1')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-300 transition-colors">
                <i id="toggle-icon-1" class="fa-regular fa-eye"></i>
              </button>
            </div>
          </div>

          <!-- Password Strength Meter -->
          <div class="space-y-2">
            <div class="flex justify-between items-center text-xs">
              <span class="text-slate-400 font-medium">Password Strength:</span>
              <span id="strength-label" class="font-semibold text-slate-500">Too Weak</span>
            </div>
            <div class="grid grid-cols-4 gap-1.5 h-1.5 w-full bg-slate-950/80 rounded-full p-0.5 border border-slate-800">
              <div id="bar-1" class="h-full rounded-full bg-slate-800 transition-all duration-300"></div>
              <div id="bar-2" class="h-full rounded-full bg-slate-800 transition-all duration-300"></div>
              <div id="bar-3" class="h-full rounded-full bg-slate-800 transition-all duration-300"></div>
              <div id="bar-4" class="h-full rounded-full bg-slate-800 transition-all duration-300"></div>
            </div>
          </div>

          <!-- Dynamic Checklist Requirements -->
          <div class="bg-slate-950/40 border border-slate-800/80 rounded-xl p-3.5 space-y-2.5">
            <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Must contain at least:</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
              <div id="req-length" class="flex items-center space-x-2 text-slate-500 transition-colors">
                <i class="fa-solid fa-circle-check text-xs"></i>
                <span>8+ characters</span>
              </div>
              <div id="req-upper" class="flex items-center space-x-2 text-slate-500 transition-colors">
                <i class="fa-solid fa-circle-check text-xs"></i>
                <span>Uppercase letter</span>
              </div>
              <div id="req-number" class="flex items-center space-x-2 text-slate-500 transition-colors">
                <i class="fa-solid fa-circle-check text-xs"></i>
                <span>One number</span>
              </div>
              <div id="req-special" class="flex items-center space-x-2 text-slate-500 transition-colors">
                <i class="fa-solid fa-circle-check text-xs"></i>
                <span>Special character</span>
              </div>
            </div>
          </div>

          <!-- Confirm Password Field -->
          <div class="space-y-1.5">
            <label for="confirm-password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Confirm Password</label>
            <div class="relative rounded-xl custom-ring transition-all">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                <i class="fa-solid fa-shield-halved"></i>
              </div>
              <input type="password" id="confirm-password" required placeholder="••••••••••••" 
                oninput="validateMatch()"
                class="w-full pl-10 pr-10 py-3 bg-slate-950/60 border border-slate-800 rounded-xl text-slate-100 placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm transition-colors">
              <button type="button" onclick="togglePasswordVisibility('confirm-password', 'toggle-icon-2')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-500 hover:text-slate-300 transition-colors">
                <i id="toggle-icon-2" class="fa-regular fa-eye"></i>
              </button>
            </div>
            <p id="match-error" class="hidden text-xs text-rose-400 mt-1 flex items-center gap-1">
              <i class="fa-solid fa-circle-exclamation"></i> Passwords do not match.
            </p>
          </div>

          <!-- Submit Button -->
          <button type="submit" id="reset-submit-btn" disabled class="w-full py-3.5 px-4 bg-slate-800 text-slate-500 font-medium rounded-xl text-sm transition-all duration-200 cursor-not-allowed flex items-center justify-center space-x-2">
            <span>Reset Password</span>
          </button>
        </form>

        <div class="pt-2 text-center">
          <button onclick="switchStep(1)" class="inline-flex items-center text-xs font-medium text-slate-400 hover:text-slate-200 transition-colors gap-2">
            <i class="fa-solid fa-arrow-left text-xs"></i>
            <span>Back to Request Reset</span>
          </button>
        </div>
      </section>


      <!-- STEP 3: SUCCESS CONFIRMATION -->
      <section id="step-3" class="hidden space-y-6 text-center animate-fade-in py-2">
        <div class="relative inline-flex items-center justify-center">
          <div class="w-20 h-20 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center">
            <i class="fa-solid fa-check text-3xl"></i>
          </div>
          <div class="absolute -bottom-1 -right-1 w-7 h-7 bg-emerald-500 rounded-full flex items-center justify-center text-slate-950 shadow-lg">
            <i class="fa-solid fa-shield text-xs"></i>
          </div>
        </div>

        <div class="space-y-2">
          <h1 class="text-2xl font-bold tracking-tight text-white">Password Reset Complete</h1>
          <p class="text-sm text-slate-400 leading-relaxed">Your password has been successfully updated. You can now use your new password to sign in to your account.</p>
        </div>

        <div class="p-4 bg-slate-950/60 border border-slate-800/80 rounded-xl text-left flex items-start space-x-3 text-xs text-slate-400">
          <i class="fa-solid fa-circle-info text-blue-400 text-sm mt-0.5"></i>
          <span>For security reasons, you have been logged out of all active sessions across all devices.</span>
        </div>

        <div class="space-y-3 pt-2">
          <a href="login.html" class="block w-full py-3.5 px-4 text-center bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-medium rounded-xl text-sm transition-all duration-200 shadow-lg shadow-blue-600/25 active:scale-[0.99]">
            Back to Sign In
          </a>
        </div>
      </section>

    </div>
  </main>

  <!-- Notification Toast Container -->
  <div id="toast" class="fixed bottom-6 right-6 z-50 transform translate-y-20 opacity-0 transition-all duration-300 pointer-events-none">
    <div class="bg-slate-900 border border-slate-700 text-slate-200 px-4 py-3 rounded-xl shadow-xl flex items-center space-x-3 text-sm">
      <div id="toast-icon" class="text-blue-400">
        <i class="fa-solid fa-paper-plane"></i>
      </div>
      <span id="toast-message">Reset link sent to your email!</span>
    </div>
  </div>

  <script>
    // --- State Management & Step Navigation ---
    let currentStep = 1;

    function switchStep(step) {
      currentStep = step;

      // Hide all steps
      document.getElementById('step-1').classList.add('hidden');
      document.getElementById('step-2').classList.add('hidden');
      document.getElementById('step-3').classList.add('hidden');

      // Show targeted step
      document.getElementById(`step-${step}`).classList.remove('hidden');

      // Update Navigation Buttons Style
      [1, 2, 3].forEach(i => {
        const btn = document.getElementById(`step-btn-${i}`);
        if (i === step) {
          btn.className = "px-3 py-1.5 rounded-full transition-all duration-200 text-white bg-blue-600 font-medium shadow-sm";
        } else {
          btn.className = "px-3 py-1.5 rounded-full transition-all duration-200 hover:text-white text-slate-400";
        }
      });
    }

    // --- Toggle Password Visibility ---
    function togglePasswordVisibility(inputId, iconId) {
      const input = document.getElementById(inputId);
      const icon = document.getElementById(iconId);

      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    }

    // --- Form 1: Request Reset Handler ---
    function handleRequestSubmit(e) {
      e.preventDefault();
      const emailInput = document.getElementById('request-email');
      const emailError = document.getElementById('email-error');
      const submitBtn = document.getElementById('request-submit-btn');

      const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      
      if (!emailPattern.test(emailInput.value)) {
        emailError.classList.remove('hidden');
        return;
      }
      emailError.classList.add('hidden');

      // Button Loading state animation
      submitBtn.disabled = true;
      submitBtn.innerHTML = `<i class="fa-solid fa-spinner animate-spin text-sm"></i><span>Sending...</span>`;

      setTimeout(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = `<span>Send Reset Link</span><i class="fa-solid fa-arrow-right text-xs"></i>`;
        showToast("Reset link sent to your email!");
        // Auto advance to step 2 after requesting reset for UX demo
        setTimeout(() => switchStep(2), 1000);
      }, 1200);
    }

    // --- Form 2: Password Validation & Strength Meter ---
    let isPasswordValid = false;

    function validatePassword() {
      const val = document.getElementById('new-password').value;

      // Checks
      const hasLength = val.length >= 8;
      const hasUpper = /[A-Z]/.exec(val) !== null;
      const hasNumber = /[0-9]/.exec(val) !== null;
      const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.exec(val) !== null;

      // Update Requirement UI Flags
      updateRequirement('req-length', hasLength);
      updateRequirement('req-upper', hasUpper);
      updateRequirement('req-number', hasNumber);
      updateRequirement('req-special', hasSpecial);

      // Score Calculation
      let score = 0;
      if (hasLength) score++;
      if (hasUpper) score++;
      if (hasNumber) score++;
      if (hasSpecial) score++;

      // Update Strength Meter Bars & Labels
      updateStrengthMeter(score, val.length > 0);

      isPasswordValid = hasLength && hasUpper && hasNumber && hasSpecial;
      validateMatch();
    }

    function updateRequirement(elementId, isValid) {
      const el = document.getElementById(elementId);
      if (isValid) {
        el.className = "flex items-center space-x-2 text-emerald-400 transition-colors font-medium";
      } else {
        el.className = "flex items-center space-x-2 text-slate-500 transition-colors";
      }
    }

    function updateStrengthMeter(score, hasInput) {
      const label = document.getElementById('strength-label');
      const bar1 = document.getElementById('bar-1');
      const bar2 = document.getElementById('bar-2');
      const bar3 = document.getElementById('bar-3');
      const bar4 = document.getElementById('bar-4');

      const bars = [bar1, bar2, bar3, bar4];
      
      // Reset classes
      bars.forEach(b => b.className = "h-full rounded-full bg-slate-800 transition-all duration-300");

      if (!hasInput) {
        label.innerText = "Too Weak";
        label.className = "font-semibold text-slate-500";
        return;
      }

      switch (score) {
        case 1:
          label.innerText = "Weak";
          label.className = "font-semibold text-rose-400";
          bar1.className = "h-full rounded-full bg-rose-500 transition-all duration-300";
          break;
        case 2:
          label.innerText = "Fair";
          label.className = "font-semibold text-amber-400";
          bar1.className = "h-full rounded-full bg-amber-500 transition-all duration-300";
          bar2.className = "h-full rounded-full bg-amber-500 transition-all duration-300";
          break;
        case 3:
          label.innerText = "Good";
          label.className = "font-semibold text-blue-400";
          bar1.className = "h-full rounded-full bg-blue-500 transition-all duration-300";
          bar2.className = "h-full rounded-full bg-blue-500 transition-all duration-300";
          bar3.className = "h-full rounded-full bg-blue-500 transition-all duration-300";
          break;
        case 4:
          label.innerText = "Strong";
          label.className = "font-semibold text-emerald-400";
          bar1.className = "h-full rounded-full bg-emerald-500 transition-all duration-300";
          bar2.className = "h-full rounded-full bg-emerald-500 transition-all duration-300";
          bar3.className = "h-full rounded-full bg-emerald-500 transition-all duration-300";
          bar4.className = "h-full rounded-full bg-emerald-500 transition-all duration-300";
          break;
      }
    }

    function validateMatch() {
      const pass = document.getElementById('new-password').value;
      const confirmPass = document.getElementById('confirm-password').value;
      const matchError = document.getElementById('match-error');
      const submitBtn = document.getElementById('reset-submit-btn');

      const isMatching = pass === confirmPass && confirmPass.length > 0;

      if (confirmPass.length > 0 && !isMatching) {
        matchError.classList.remove('hidden');
      } else {
        matchError.classList.add('hidden');
      }

      // Enable submit button if both criteria pass
      if (isPasswordValid && isMatching) {
        submitBtn.disabled = false;
        submitBtn.className = "w-full py-3.5 px-4 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-medium rounded-xl text-sm transition-all duration-200 shadow-lg shadow-blue-600/25 active:scale-[0.99] cursor-pointer flex items-center justify-center space-x-2";
      } else {
        submitBtn.disabled = true;
        submitBtn.className = "w-full py-3.5 px-4 bg-slate-800 text-slate-500 font-medium rounded-xl text-sm transition-all duration-200 cursor-not-allowed flex items-center justify-center space-x-2";
      }
    }

    // --- Form 2 Submission ---
    function handleResetSubmit(e) {
      e.preventDefault();
      const submitBtn = document.getElementById('reset-submit-btn');

      submitBtn.disabled = true;
      submitBtn.innerHTML = `<i class="fa-solid fa-spinner animate-spin text-sm"></i><span>Updating Password...</span>`;

      setTimeout(() => {
        switchStep(3);
        showToast("Password updated successfully!");
      }, 1200);
    }

    // --- Notification Toast ---
    function showToast(msg) {
      const toast = document.getElementById('toast');
      const toastMsg = document.getElementById('toast-message');
      
      toastMsg.innerText = msg;
      toast.classList.remove('translate-y-20', 'opacity-0');
      
      setTimeout(() => {
        toast.classList.add('translate-y-20', 'opacity-0');
      }, 3500);
    }
  </script>
</body>
</html>