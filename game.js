// game.js - Complete Aviator Game JavaScript

document.addEventListener('DOMContentLoaded', () => {
    // ==================== DOM Elements ====================
    const plane = document.getElementById('plane');
    const multiplierEl = document.getElementById('multiplier');
    const startBtn = document.getElementById('startBtn');
    const cashoutBtn = document.getElementById('cashoutBtn');
    const betAmount = document.getElementById('betAmount');
    const balanceEl = document.getElementById('balance');
    const menuBalance = document.getElementById('menu-balance');
    const withdrawMethod = document.getElementById('withdrawMethod');
    const upiFields = document.querySelector('.upi-fields');
    const bankFields = document.querySelector('.bank-fields');
    const gameArea = document.getElementById('gameArea');
    const menuIcon = document.getElementById('menuIcon');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    
    // ==================== Game State Variables ====================
    let gameInterval;
    let multiplier = 1.0;
    let isPlaying = false;
    let crashPoint = 0;
    let currentBet = 0;
    let lastTime = null;
  
    // ==================== Initialize All Event Listeners ====================
    function initEventListeners() {
      // Hamburger Menu Toggle
      menuIcon.addEventListener('click', toggleMenu);
      overlay.addEventListener('click', closeMenu);
      
      // Withdraw Method Change
      withdrawMethod.addEventListener('change', handleWithdrawMethodChange);
      
      // Form Submissions
      document.getElementById('withdrawForm').addEventListener('submit', handleWithdrawSubmit);
      document.getElementById('depositForm').addEventListener('submit', handleDepositSubmit);
      
      // Game Controls
      betAmount.addEventListener('input', handleBetInput);
      startBtn.addEventListener('click', startGame);
      cashoutBtn.addEventListener('click', cashout);
      
      // Notification Polling
      setInterval(pollForNotification, 10000);
      pollForNotification();
    }
  
    // ==================== Hamburger Menu Functions ====================
    function toggleMenu() {
      sidebar.classList.toggle('active');
      overlay.classList.toggle('active');
    }
  
    function closeMenu() {
      sidebar.classList.remove('active');
      overlay.classList.remove('active');
    }
  
    // ==================== Withdraw/DEPOSIT Handlers ====================
    function handleWithdrawMethodChange() {
      if (this.value === 'upi') {
        upiFields.style.display = 'block';
        bankFields.style.display = 'none';
        document.getElementById('upi_id').required = true;
        document.getElementById('account_holder').required = false;
        document.getElementById('account_no').required = false;
        document.getElementById('ifsc').required = false;
      } else {
        bankFields.style.display = 'block';
        upiFields.style.display = 'none';
        document.getElementById('upi_id').required = false;
        document.getElementById('account_holder').required = true;
        document.getElementById('account_no').required = true;
        document.getElementById('ifsc').required = true;
      }
    }
  
    async function handleWithdrawSubmit(e) {
      e.preventDefault();
      const formData = new FormData(e.target);
      try {
        const response = await fetch('process.php?action=withdraw', { 
          method: 'POST', 
          body: formData 
        });
        const data = await response.json();
        if (data.error) throw new Error(data.error);
        updateBalance(data.balance);
        showNotification("💸 Withdrawal request submitted! Please Wait 3 to 4 Hours", "success");
      } catch (error) {
        showNotification(error.message, "error");
      }
    }
  
    async function handleDepositSubmit(e) {
      e.preventDefault();
      const formData = new FormData(e.target);
      try {
        const response = await fetch('process.php?action=deposit', { 
          method: 'POST', 
          body: formData 
        });
        const data = await response.json();
        if (data.error) throw new Error(data.error);
        updateBalance(data.balance);
        showNotification("💰 Deposit submitted for approval! Please Wait 3 to 4 Hours", "success");
      } catch (error) {
        showNotification(error.message, "error");
      }
    }
  
    // ==================== GAME LOGIC ====================
    function handleBetInput() {
      betAmount.value = betAmount.value.replace(/\D/g, '');
      if (parseInt(betAmount.value) > parseInt(balanceEl.textContent)) {
        betAmount.value = balanceEl.textContent;
      }
    }
  
    async function startGame() {
      if (isPlaying) return;
  
      currentBet = parseInt(betAmount.value);
      const balance = parseInt(balanceEl.textContent);
  
      if (currentBet < 10 || currentBet > balance) {
        showNotification(currentBet < 10 ? "Minimum bet is ₹10" : "Insufficient balance", "error");
        return;
      }
  
      // Set crash point (higher bets get lower multipliers)
      const rand = Math.random();
      crashPoint = rand < 0.07
        ? (Math.random() * 2 + 2).toFixed(2)   // 7% chance for 2x-4x
        : currentBet < 100
          ? (Math.random() * 0.9 + 1).toFixed(2)  // 1x-1.9x for small bets
          : (Math.random() * 0.7 + 1).toFixed(2); // 1x-1.7x for larger bets
  
      isPlaying = true;
      startBtn.disabled = true;
      cashoutBtn.disabled = false;
      multiplier = 1.0;
      lastTime = null;
      
      plane.style.display = 'block';
      plane.style.bottom = '0px';
      multiplierEl.textContent = 'x1.00';
      gameInterval = requestAnimationFrame(animateGame);
  
      try {
        const response = await fetch(`process.php?action=start_bet&bet=${currentBet}`);
        const data = await response.json();
        if (data.error) throw new Error(data.error);
        updateBalance(data.balance);
      } catch (error) {
        console.error("Bet error:", error);
      }
    }
  
    function animateGame(timestamp) {
      if (!lastTime) lastTime = timestamp;
      const delta = timestamp - lastTime;
  
      if (delta >= 30) {
        multiplier += 0.012;
        multiplierEl.textContent = `x${multiplier.toFixed(2)}`;
  
        let currentBottom = parseFloat(plane.style.bottom) || 0;
        let newBottom = currentBottom + 1.5;
  
        if (newBottom + plane.offsetHeight > gameArea.clientHeight) {
          newBottom = gameArea.clientHeight - plane.offsetHeight;
        }
  
        plane.style.bottom = newBottom + 'px';
  
        if (multiplier >= crashPoint) {
          endGame(false);
          return;
        }
  
        lastTime = timestamp;
      }
  
      if (isPlaying) {
        gameInterval = requestAnimationFrame(animateGame);
      }
    }
  
    async function cashout() {
      if (!isPlaying) return;
  
      const winAmount = Math.floor(currentBet * multiplier);
      isPlaying = false;
      cancelAnimationFrame(gameInterval);
      
      showNotification(`🎉 Won ₹${winAmount} at x${multiplier.toFixed(2)}!`, "success");
      resetGame();
  
      try {
        const response = await fetch(`process.php?action=cashout&amount=${winAmount}`);
        const data = await response.json();
        if (data.error) throw new Error(data.error);
        updateBalance(data.balance);
      } catch (error) {
        showNotification("Cashout error: " + error.message, "error");
      }
    }
  
    async function endGame(manualCashout) {
      cancelAnimationFrame(gameInterval);
      if (!isPlaying) return;
      isPlaying = false;
  
      if (!manualCashout) {
        showNotification(`💥 Crashed at x${multiplier.toFixed(2)}! Lost ₹${currentBet}`, "error");
        try {
          const response = await fetch(`process.php?action=crash&bet=${currentBet}`);
          const data = await response.json();
          updateBalance(data.balance);
        } catch (error) {
          console.error("Crash error:", error);
        }
      }
      resetGame();
    }
  
    function resetGame() {
      isPlaying = false;
      startBtn.disabled = false;
      cashoutBtn.disabled = true;
      plane.style.display = 'none';
      multiplierEl.textContent = 'x1.00';
    }
  
    // ==================== UTILITY FUNCTIONS ====================
    function updateBalance(newBalance) {
      balanceEl.textContent = newBalance;
      menuBalance.textContent = newBalance;
      betAmount.max = newBalance;
    }
  
    function showNotification(message, type = "success") {
      const notif = document.getElementById('notification');
      notif.textContent = message;
      notif.className = `notification ${type} show`;
  
      setTimeout(() => {
        notif.classList.remove('show');
        notif.classList.add('hidden');
      }, 800);
    }
  
    function pollForNotification() {
      fetch('check_notification.php')
        .then(res => res.json())
        .then(data => {
          if (data.message) {
            showLiveNotification(data.message);
          }
        })
        .catch(err => console.error("Notification error:", err));
    }
  
    function showLiveNotification(msg) {
      const notif = document.createElement('div');
      notif.className = 'user-toast';
      notif.textContent = '🔔 ' + msg;
      document.body.appendChild(notif);
  
      setTimeout(() => {
        notif.classList.add('hide');
        setTimeout(() => notif.remove(), 500);
      }, 2000);
    }
  
    // ==================== INITIALIZE EVERYTHING ====================
    initEventListeners();
  });