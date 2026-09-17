(function() {
    function initBell() {
        const bellBtn = document.getElementById('nav-notification-bell');
        if (!bellBtn) return;

        const badge = document.getElementById('nav-notification-badge');
        const dropdown = document.getElementById('nav-notification-dropdown');
        const list = document.getElementById('nav-notification-list');
        const markAllBtn = document.getElementById('nav-notification-mark-all');

        let unreadCount = 0;

        function fetchNotifications() {
            // Using absolute path just in case the server uses URL rewriting, and appending cache buster
            const apiUrl = (window.location.pathname.includes('/System/') 
                ? '/System/api_notifications.php?action=fetch' 
                : 'api_notifications.php?action=fetch') + '&t=' + Date.now();
                
            fetch(apiUrl)
                .then(r => {
                    if (!r.ok) throw new Error('Network response was not ok');
                    return r.text();
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.status === 'success') {
                            unreadCount = data.unread_count;
                            updateBadge();
                            renderList(data.notifications);
                        }
                    } catch (e) {
                        console.error('Failed to parse JSON:', e, 'Response text:', text);
                    }
                })
                .catch(err => console.error('Error fetching notifications:', err));
        }

        function updateBadge() {
            if (badge) {
                if (unreadCount > 0) {
                    badge.textContent = unreadCount > 9 ? '+9' : unreadCount;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            }
        }

        function renderList(notifications) {
            if (!list) return;
            list.innerHTML = '';
            if (notifications.length === 0) {
                list.innerHTML = '<div style="padding:1rem; text-align:center; color:#6b7280; font-size:0.875rem;">لا توجد إشعارات حالياً</div>';
                return;
            }

            notifications.forEach(n => {
                const item = document.createElement('a');
                item.href = n.link ? n.link : '#';
                item.style.textDecoration = 'none';
                item.style.display = 'block';
                item.style.padding = '0.75rem 1rem';
                item.style.borderBottom = '1px solid #e5e7eb';
                item.style.transition = 'background-color 0.2s';
                
                if (n.is_read == 0) {
                    item.style.backgroundColor = '#eff6ff';
                }
                
                item.onmouseover = function() { item.style.backgroundColor = '#f9fafb'; };
                item.onmouseout = function() { item.style.backgroundColor = (n.is_read == 0) ? '#eff6ff' : 'transparent'; };
                
                let dot = n.is_read == 0 ? '<span style="display:inline-block; width:8px; height:8px; border-radius:50%; background:#3b82f6; margin-top:6px; flex-shrink:0;"></span>' : '';
                
                item.innerHTML = `
                    <div style="display:flex; gap:0.75rem;">
                        ${dot}
                        <div>
                            <div style="font-size:0.875rem; font-weight:600; color:#1f2937;">${n.title}</div>
                            <div style="font-size:0.75rem; color:#4b5563; margin-top:0.25rem;">${n.message}</div>
                            <div style="font-size:10px; color:#9ca3af; margin-top:0.25rem;">${n.created_at}</div>
                        </div>
                    </div>
                `;

                item.addEventListener('click', function(e) {
                    if (n.is_read == 0) {
                        e.preventDefault();
                        const markUrl = (window.location.pathname.includes('/System/') 
                            ? '/System/api_notifications.php?action=mark_read' 
                            : 'api_notifications.php?action=mark_read') + '&t=' + Date.now();
                            
                        fetch(markUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'id=' + n.id
                        }).then(() => {
                            window.location.href = item.href;
                        });
                    }
                });

                list.appendChild(item);
            });
        }

        if (bellBtn && dropdown) {
            // Force hide initially via inline style to be safe
            dropdown.style.display = 'none';
            dropdown.classList.remove('hidden'); // Remove class to prevent conflicts
            
            bellBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (dropdown.style.display === 'none' || dropdown.style.display === '') {
                    dropdown.style.display = 'block';
                } else {
                    dropdown.style.display = 'none';
                }
            });

            document.addEventListener('click', function(e) {
                if (!bellBtn.contains(e.target) && !dropdown.contains(e.target)) {
                    dropdown.style.display = 'none';
                }
            });
        }

        if (markAllBtn) {
            markAllBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const markAllUrl = (window.location.pathname.includes('/System/') 
                    ? '/System/api_notifications.php?action=mark_all_read' 
                    : 'api_notifications.php?action=mark_all_read') + '&t=' + Date.now();
                    
                fetch(markAllUrl, { method: 'POST' })
                    .then(() => { fetchNotifications(); });
            });
        }

        // Initial fetch and poll every 60s
        fetchNotifications();
        setInterval(fetchNotifications, 60000);
    }

    // Ensure it only runs once
    let initialized = false;
    function safeInit() {
        if (!initialized) {
            initialized = true;
            initBell();
        }
    }

    safeInit();
    document.addEventListener('DOMContentLoaded', safeInit);
})();
