// ملف: navbar.js - Navbar موحد للصفحات HTML
async function loadNavbar() {
    try {
        const response = await fetch('get_user_info.php');
        const data = await response.json();
        
        const navbar = document.getElementById('main-navbar');
        if (!navbar) return;
        
        const currentPage = window.location.pathname.split('/').pop().replace('.html', '').replace('.php', '');
        
        navbar.innerHTML = `
            <div style="display: flex; align-items: center; gap: 2rem;">
                <a href="Home1.html" style="font-size: 2rem; font-weight: 900; color: #4b6b2f; letter-spacing: 1px; text-transform: uppercase; text-decoration: none; transition: all 0.3s ease;">
                    لوحة المسوق
                </a>
                <div style="display: flex; gap: 1.5rem; align-items: center;">
                    <a href="Home1.html" style="font-weight: 600; color: ${currentPage === 'Home1' || currentPage === 'home' ? '#4b6b2f' : '#1e293b'}; text-decoration: none; font-size: 1.2rem; transition: all 0.3s ease; position: relative; padding-bottom: 5px;">
                        الرئيسية
                        ${currentPage === 'Home1' || currentPage === 'home' ? '<span style="position: absolute; bottom: 0; left: 0; width: 100%; height: 2px; background-color: #4b6b2f;"></span>' : ''}
                    </a>
                    <a href="product.html" style="font-weight: 600; color: ${currentPage === 'product' ? '#4b6b2f' : '#1e293b'}; text-decoration: none; font-size: 1.2rem; transition: all 0.3s ease; position: relative; padding-bottom: 5px;">
                        المنتجات
                        ${currentPage === 'product' ? '<span style="position: absolute; bottom: 0; left: 0; width: 100%; height: 2px; background-color: #4b6b2f;"></span>' : ''}
                    </a>
                    <a href="orders.html" style="font-weight: 600; color: ${currentPage === 'orders' ? '#4b6b2f' : '#1e293b'}; text-decoration: none; font-size: 1.2rem; transition: all 0.3s ease; position: relative; padding-bottom: 5px;">
                        الطلبات
                        ${currentPage === 'orders' ? '<span style="position: absolute; bottom: 0; left: 0; width: 100%; height: 2px; background-color: #4b6b2f;"></span>' : ''}
                    </a>
                    <a href="withdrawals.php" style="font-weight: 600; color: ${currentPage === 'withdrawals' ? '#4b6b2f' : '#1e293b'}; text-decoration: none; font-size: 1.2rem; transition: all 0.3s ease; position: relative; padding-bottom: 5px;">
                        السحب
                        ${currentPage === 'withdrawals' ? '<span style="position: absolute; bottom: 0; left: 0; width: 100%; height: 2px; background-color: #4b6b2f;"></span>' : ''}
                    </a>
                </div>
            </div>
            <div style="display: flex; align-items: center; gap: 1rem;">
                ${data.logged_in ? `
                    <a href="profile.php" style="display: flex; align-items: center; gap: 0.5rem; text-decoration: none; color: #1e293b; font-weight: 600; padding: 0.5rem 1rem; border-radius: 0.5rem; transition: all 0.3s ease; background: rgba(75, 107, 47, 0.1);">
                        <i class='bx bx-user-circle' style="font-size: 1.5rem; color: #4b6b2f;"></i>
                        <span>${data.user_name || 'المستخدم'}</span>
                    </a>
                    <a href="logout.php" style="background: #ef4444; color: white; padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600; transition: all 0.3s ease;">
                        تسجيل الخروج
                    </a>
                ` : `
                    <a href="login.html" style="background: #4b6b2f; color: white; padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600; transition: all 0.3s ease;">
                        تسجيل الدخول
                    </a>
                    <a href="register.html" style="background: transparent; color: #4b6b2f; border: 2px solid #4b6b2f; padding: 0.5rem 1rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600; transition: all 0.3s ease;">
                        إنشاء حساب
                    </a>
                `}
            </div>
        `;
    } catch (error) {
        console.error('Error loading navbar:', error);
    }
}

