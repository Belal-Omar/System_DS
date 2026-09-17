/**
 * نظام الإشعارات الاحترافي
 * Professional Toast Notifications System
 * يتوافق مع تصميم المشروع
 */

(function() {
    'use strict';

    // إنشاء container للإشعارات إذا لم يكن موجوداً
    function getOrCreateContainer() {
        let container = document.getElementById('notifications-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notifications-container';
            container.style.cssText = `
                position: fixed;
                top: 100px;
                left: 50%;
                transform: translateX(-50%);
                z-index: 99999;
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 10px;
                pointer-events: none;
                max-width: 90vw;
            `;
            document.body.appendChild(container);
        }
        return container;
    }

    // أيقونات لكل نوع
    const icons = {
        success: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
        error: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>',
        warning: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
        info: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>'
    };

    // ألوان متوافقة مع تصميم المشروع
    const colors = {
        success: {
            bg: 'linear-gradient(135deg, #4b6b2f 0%, #3a5524 100%)',
            border: '#4b6b2f',
            text: '#ffffff'
        },
        error: {
            bg: 'linear-gradient(135deg, #dc2626 0%, #b91c1c 100%)',
            border: '#dc2626',
            text: '#ffffff'
        },
        warning: {
            bg: 'linear-gradient(135deg, #f59e0b 0%, #d97706 100%)',
            border: '#f59e0b',
            text: '#ffffff'
        },
        info: {
            bg: 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)',
            border: '#3b82f6',
            text: '#ffffff'
        }
    };

    /**
     * عرض إشعار
     * @param {string} message - نص الرسالة
     * @param {string} type - نوع الإشعار: success, error, warning, info
     * @param {number} duration - مدة العرض بالميلي ثانية (افتراضي 6000)
     */
    function showNotification(message, type = 'info', duration = 6000) {
        const container = getOrCreateContainer();
        const color = colors[type] || colors.info;
        const icon = icons[type] || icons.info;

        // إنشاء عنصر الإشعار
        const notification = document.createElement('div');
        notification.className = 'toast-notification';
        notification.style.cssText = `
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 24px;
            background: ${color.bg};
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2), 0 4px 12px rgba(0, 0, 0, 0.1);
            color: ${color.text};
            font-family: 'Cairo', 'Segoe UI', sans-serif;
            font-size: 15px;
            font-weight: 500;
            max-width: 400px;
            pointer-events: auto;
            cursor: pointer;
            transform: translateY(-20px);
            opacity: 0;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            direction: rtl;
            text-align: right;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        `;

        notification.innerHTML = `
            <span style="flex-shrink: 0; display: flex; align-items: center;">${icon}</span>
            <span style="flex: 1; line-height: 1.5;">${message}</span>
            <button style="
                background: rgba(255,255,255,0.2);
                border: none;
                color: white;
                width: 28px;
                height: 28px;
                border-radius: 50%;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 18px;
                flex-shrink: 0;
                transition: background 0.2s;
            " onmouseover="this.style.background='rgba(255,255,255,0.3)'" onmouseout="this.style.background='rgba(255,255,255,0.2)'">&times;</button>
        `;

        container.appendChild(notification);

        // تحريك للظهور
        requestAnimationFrame(() => {
            notification.style.transform = 'translateY(0)';
            notification.style.opacity = '1';
        });

        // إغلاق عند النقر
        notification.addEventListener('click', () => {
            closeNotification(notification);
        });

        // إغلاق تلقائي
        const timeoutId = setTimeout(() => {
            closeNotification(notification);
        }, duration);

        // إيقاف المؤقت عند التمرير
        notification.addEventListener('mouseenter', () => {
            clearTimeout(timeoutId);
        });

        notification.addEventListener('mouseleave', () => {
            setTimeout(() => {
                closeNotification(notification);
            }, 1000);
        });

        return notification;
    }

    /**
     * إغلاق إشعار
     */
    function closeNotification(notification) {
        if (!notification || notification.classList.contains('closing')) return;
        
        notification.classList.add('closing');
        notification.style.transform = 'translateY(-20px) scale(0.9)';
        notification.style.opacity = '0';

        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 400);
    }

    /**
     * عرض نافذة تأكيد احترافية
     * @param {string} message - رسالة التأكيد
     * @param {function} onConfirm - دالة الاستدعاء عند الموافقة
     */
    function showConfirm(message, onConfirm) {
        const overlay = document.createElement('div');
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 200000;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            font-family: 'Cairo', sans-serif;
        `;
        
        const card = document.createElement('div');
        card.style.cssText = `
            background: white;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 400px;
            text-align: center;
            transform: scale(0.8);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        `;
        
        const icon = `
            <div style="
                width: 60px;
                height: 60px;
                background: #fef3c7;
                color: #d97706;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
                font-size: 30px;
            ">
                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>
        `;
        
        const title = `<h3 style="margin: 0 0 10px; color: #1f2937; font-size: 20px; font-weight: 700;">تأكيد الإجراء</h3>`;
        const text = `<p style="margin: 0 0 25px; color: #6b7280; font-size: 16px; line-height: 1.5;">${message}</p>`;
        
        const btnContainer = document.createElement('div');
        btnContainer.style.cssText = `display: flex; gap: 12px; justify-content: center;`;
        
        const confirmBtn = document.createElement('button');
        confirmBtn.textContent = 'نعم، متأكد';
        confirmBtn.style.cssText = `
            background: #dc2626;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            flex: 1;
            transition: all 0.2s;
            box-shadow: 0 4px 6px -1px rgba(220, 38, 38, 0.2);
        `;
        
        const cancelBtn = document.createElement('button');
        cancelBtn.textContent = 'إلغاء';
        cancelBtn.style.cssText = `
            background: #f3f4f6;
            color: #374151;
            border: none;
            padding: 10px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            flex: 1;
            transition: all 0.2s;
        `;
        
        // Hover effects
        confirmBtn.onmouseenter = () => confirmBtn.style.transform = 'translateY(-2px)';
        confirmBtn.onmouseleave = () => confirmBtn.style.transform = 'translateY(0)';
        cancelBtn.onmouseenter = () => cancelBtn.style.background = '#e5e7eb';
        cancelBtn.onmouseleave = () => cancelBtn.style.background = '#f3f4f6';
        
        function close() {
            overlay.style.opacity = '0';
            card.style.transform = 'scale(0.8)';
            setTimeout(() => {
                if (overlay.parentNode) {
                    overlay.parentNode.removeChild(overlay);
                }
            }, 300);
        }

        confirmBtn.onclick = () => {
            close();
            if(onConfirm) onConfirm();
        };
        
        cancelBtn.onclick = close;
        overlay.onclick = (e) => {
            if (e.target === overlay) close();
        };
        
        btnContainer.appendChild(cancelBtn);
        btnContainer.appendChild(confirmBtn);
        
        card.innerHTML = icon + title + text;
        card.appendChild(btnContainer);
        overlay.appendChild(card);
        document.body.appendChild(overlay);
        
        // Animate in
        requestAnimationFrame(() => {
            overlay.style.opacity = '1';
            card.style.transform = 'scale(1)';
        });
    }

    // دوال مختصرة للاستخدام السهل
    window.showNotification = showNotification;
    window.showSuccess = (msg, duration) => showNotification(msg, 'success', duration);
    window.showError = (msg, duration) => showNotification(msg, 'error', duration);
    window.showWarning = (msg, duration) => showNotification(msg, 'warning', duration);
    window.showInfo = (msg, duration) => showNotification(msg, 'info', duration);
    window.showConfirm = showConfirm;

    // للتوافق مع الكود القديم - استبدال alert
    window.customAlert = showNotification;

})();
