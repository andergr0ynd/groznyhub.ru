// Основной JavaScript файл
document.addEventListener('DOMContentLoaded', function() {
    // Плавная анимация появления элементов
    const fadeElements = document.querySelectorAll('.fade-in');
    
    fadeElements.forEach((element, index) => {
        setTimeout(() => {
            element.style.opacity = '0';
            element.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                element.style.opacity = '1';
            }, 50);
        }, index * 100);
    });

    // Обработка ошибок
    const errorMessages = document.querySelectorAll('.message-error');
    if (errorMessages.length > 0) {
        setTimeout(() => {
            errorMessages.forEach(msg => {
                msg.style.transition = 'opacity 0.5s ease';
                msg.style.opacity = '0';
                setTimeout(() => {
                    msg.style.display = 'none';
                }, 500);
            });
        }, 5000);
    }

    // Hover-эффекты только на устройствах с мышью
    if (window.matchMedia('(hover: hover)').matches) {
        const buttons = document.querySelectorAll('.btn');
        buttons.forEach(btn => {
            btn.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-2px)';
            });

            btn.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    }

    // Плавная прокрутка
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });

    // Функция для обновления времени и даты в футере
    function updateFooterDateTime() {
        const footerElement = document.getElementById('footerDateTime');
        if (footerElement) {
            const now = new Date();
            const timeString = now.toLocaleTimeString('ru-RU', { 
                hour: '2-digit', 
                minute: '2-digit',
                second: '2-digit'
            });
            const dateString = now.toLocaleDateString('ru-RU', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            const isMobile = window.matchMedia('(max-width: 768px)').matches;
            if (isMobile) {
                footerElement.textContent = now.toLocaleTimeString('ru-RU', {
                    hour: '2-digit',
                    minute: '2-digit',
                }) + ' · ' + now.toLocaleDateString('ru-RU', {
                    day: 'numeric',
                    month: 'short',
                });
            } else {
                footerElement.textContent = `Время: ${timeString} | Дата: ${dateString}`;
            }
        }
    }
    
    // Обновляем время и дату в футере
    updateFooterDateTime();
    // Обновляем каждую секунду
    setInterval(updateFooterDateTime, 1000);
});

