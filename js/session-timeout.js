/**
 * Session Timeout Handler
 * Automatically logs out user after 1 hour of inactivity
 */

let inactivityTime = function () {
    let time;
    const warningTime = 300000; // 5 minutes before logout (in milliseconds)
    const logoutTime = 3600000;  // 1 hour (in milliseconds)
    let warningTimer;
    let logoutTimer;

    // Show warning modal
    function showWarning() {
        // Create modal if it doesn't exist
        if (!document.getElementById('inactive-warning')) {
            const modalHTML = `
                <div class="modal fade" id="inactive-warning" tabindex="-1" role="dialog" aria-labelledby="inactiveWarningLabel" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <div class="modal-content">
                            <div class="modal-header bg-warning">
                                <h5 class="modal-title" id="inactiveWarningLabel">Session About to Expire</h5>
                            </div>
                            <div class="modal-body">
                                <p>Your session will expire in 5 minutes due to inactivity.</p>
                                <p>Would you like to stay signed in?</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" id="stayLoggedIn">Stay Logged In</button>
                                <button type="button" class="btn btn-primary" id="logoutNow">Logout Now</button>
                            </div>
                        </div>
                    </div>
                </div>`;
            document.body.insertAdjacentHTML('beforeend', modalHTML);
            
            // Add event listeners to modal buttons
            document.getElementById('stayLoggedIn').addEventListener('click', resetTimers);
            document.getElementById('logoutNow').addEventListener('click', () => {
                window.location.href = 'logout.php';
            });
        }
        
        // Show the Bootstrap modal
        $('#inactive-warning').modal('show');
    }

    // Reset timers on user activity
    function resetTimers() {
        // Hide the warning modal if it's open
        const warningModal = document.getElementById('inactive-warning');
        if (warningModal) {
            $(warningModal).modal('hide');
        }
        
        // Clear existing timers
        clearTimeout(warningTimer);
        clearTimeout(logoutTimer);
        
        // Send keepalive request to update server-side timestamp
        updateActivityTimestamp();
        
        // Set new timers
        warningTimer = setTimeout(showWarning, logoutTime - warningTime);
        logoutTimer = setTimeout(logout, logoutTime);
    }
    
    // Logout function
    function logout() {
        window.location.href = 'logout.php?timeout=1';
    }
    
    // Update server-side activity timestamp
    function updateActivityTimestamp() {
        fetch('keepalive.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') {
                console.error('Failed to update activity timestamp');
            }
        })
        .catch(error => console.error('Error:', error));
    }
    
    // Set up event listeners for user activity
    function setupEventListeners() {
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'];
        events.forEach(event => {
            document.addEventListener(event, resetTimers, false);
        });
    }
    
    // Initialize the timers and event listeners
    function init() {
        setupEventListeners();
        resetTimers();
    }

    // Public API
    return {
        init: init,
        resetTimers: resetTimers
    };
}();

// Initialize the session timeout handler when the document is ready
document.addEventListener('DOMContentLoaded', function() {
    inactivityTime.init();
});
