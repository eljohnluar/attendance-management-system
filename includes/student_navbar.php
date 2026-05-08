<nav class="navbar">
    <div class="nav-left">
        <button class="navbar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <div class="nav-title">
            <i class="fas fa-user-graduate"></i> Student Portal
        </div>
    </div>
    <div class="nav-right">
        <div class="datetime">
            <i class="fas fa-clock"></i>
            <span id="currentTime"></span>
        </div>
        <a href="<?php echo SITE_URL; ?>logout.php" class="nav-btn logout">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</nav>

<style>
.navbar {
    background: var(--white);
    padding: 12px 24px;
    box-shadow: var(--shadow);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: fixed;
    top: 0;
    right: 0;
    z-index: 101;
    border-bottom: 1px solid var(--grey);
    height: var(--navbar-height, 60px);
    transition: left 0.3s ease;
}

.nav-left {
    display: flex;
    align-items: center;
    gap: 16px;
}

.nav-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.navbar-toggle {
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
    color: var(--pink);
    width: 36px;
    height: 36px;
    display: none;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s;
}

.navbar-toggle:hover {
    background: var(--grey-light);
}

.nav-title {
    font-size: 16px;
    font-weight: 500;
    color: #333;
}

.nav-title i {
    color: var(--pink);
    margin-right: 8px;
}

.datetime {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    background: var(--grey-light);
    border-radius: 40px;
    font-size: 13px;
    color: #666;
    font-family: 'Courier New', monospace;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.datetime i {
    color: var(--pink);
    font-size: 13px;
}

.nav-btn.logout {
    background: none;
    border: 1px solid var(--grey);
    padding: 6px 16px;
    border-radius: 40px;
    font-size: 13px;
    cursor: pointer;
    color: #666;
    text-decoration: none;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
    white-space: nowrap;
}

.nav-btn.logout:hover {
    background: var(--grey-light);
    border-color: var(--pink);
    color: var(--pink);
}

/* Desktop Styles */
@media (min-width: 1024px) {
    .navbar {
        left: var(--sidebar-width, 260px);
        width: calc(100% - var(--sidebar-width, 260px));
    }
    
    .navbar-toggle {
        display: none;
    }
}

/* Mobile Styles */
@media (max-width: 1023px) {
    .navbar {
        left: 0;
        width: 100%;
        padding: 12px 16px;
    }
    
    .navbar-toggle {
        display: flex;
    }
    
    .datetime span {
        display: none;
    }
    
    .datetime {
        padding: 6px 10px;
    }
    
    .nav-btn.logout span {
        display: none;
    }
    
    .nav-btn.logout {
        padding: 6px 10px;
    }
    
    .nav-title {
        font-size: 14px;
    }
}

/* Small Mobile */
@media (max-width: 480px) {
    .navbar {
        padding: 10px 12px;
    }
    
    .nav-title {
        display: none;
    }
    
    .datetime {
        padding: 6px 8px;
    }
}
</style>

<script>
// Sidebar Toggle
document.addEventListener('DOMContentLoaded', function() {
    const toggleBtn = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    
    if (toggleBtn && sidebar) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            if (overlay) {
                if (sidebar.classList.contains('open')) {
                    overlay.classList.add('active');
                    document.body.style.overflow = 'hidden';
                } else {
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            }
        });
    }
    
    // Close sidebar when clicking overlay
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
});

// Update time in 24-hour format
function updateTime() {
    const now = new Date();
    const hours = now.getHours().toString().padStart(2, '0');
    const minutes = now.getMinutes().toString().padStart(2, '0');
    const seconds = now.getSeconds().toString().padStart(2, '0');
    const timeString = `${hours}:${minutes}:${seconds}`;
    
    const timeElement = document.getElementById('currentTime');
    if (timeElement) {
        timeElement.textContent = timeString;
    }
}

updateTime();
setInterval(updateTime, 1000);
</script>