// Load SweetAlert2 dynamically if not already present
(function ensureSweetAlert(){
    if (!window.Swal) {
        const script = document.createElement('script');
        script.src = 'main/assets/js/sweetalert2.all.min.js';
        script.defer = true;
        document.head.appendChild(script);
    }
})();

function showFrontendAlert(message, type = 'info', options = {}) {
    if (window.Swal) {
        return Swal.fire({
            icon: type,
            text: message,
            confirmButtonColor: '#d97706',
            ...options
        });
    }
    return window.showFrontendAlert(message);
}

﻿// Navigation Toggle
const navToggle = document.querySelector('.nav-toggle');
const navMenu = document.querySelector('.nav-menu');

if (navToggle) {
    navToggle.addEventListener('click', () => {
        navMenu.classList.toggle('active');
    });
}

// Smooth Scrolling
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
            navMenu.classList.remove('active');
        }
    });
});

// Dashboard Tabs
const tabButtons = document.querySelectorAll('.tab-btn');
const dashboardPanels = document.querySelectorAll('.dashboard-panel');

tabButtons.forEach(button => {
    button.addEventListener('click', () => {
        const tab = button.dataset.tab;
        
        // Remove active class from all buttons and panels
        tabButtons.forEach(btn => btn.classList.remove('active'));
        dashboardPanels.forEach(panel => panel.classList.remove('active'));
        
        // Add active class to clicked button and corresponding panel
        button.classList.add('active');
        const panel = document.querySelector(`[data-panel="${tab}"]`);
        if (panel) {
            panel.classList.add('active');
        }
    });
});

// Feature Showcase Animation
const showcaseCards = document.querySelectorAll('.showcase-card');
let currentCard = 0;

if (showcaseCards.length > 0) {
    setInterval(() => {
        showcaseCards[currentCard].classList.remove('active');
        currentCard = (currentCard + 1) % showcaseCards.length;
        showcaseCards[currentCard].classList.add('active');
    }, 3000);
}

// Navbar Scroll Effect
let lastScroll = 0;
const navbar = document.querySelector('.navbar');

window.addEventListener('scroll', () => {
    const currentScroll = window.pageYOffset;
    
    if (currentScroll > 100) {
        navbar.style.boxShadow = '0 4px 30px rgba(0, 0, 0, 0.1)';
    } else {
        navbar.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.05)';
    }
    
    lastScroll = currentScroll;
});

// Contact Form Submission
const contactForm = document.getElementById('contactForm');

if (contactForm) {
    contactForm.addEventListener('submit', (e) => {
        e.preventDefault();
        
        // Get form data
        const formData = new FormData(contactForm);
        const data = Object.fromEntries(formData);
        
        // Here you would normally send this to your server
        // For now, we'll just show a success message
        showFrontendAlert('Thank you for your interest! We will contact you soon.');
        contactForm.reset();
        
        // In production, you would do something like:
        // fetch('/api/contact', {
        //     method: 'POST',
        //     headers: { 'Content-Type': 'application/json' },
        //     body: JSON.stringify(data)
        // })
        // .then(response => response.json())
        // .then(data => {
        //     showFrontendAlert('Message sent successfully!');
        //     contactForm.reset();
        // })
        // .catch(error => {
        //     showFrontendAlert('Error sending message. Please try again.');
        // });
    });
}

// Optimized Intersection Observer for Animations
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry, index) => {
        if (entry.isIntersecting) {
            // Use requestAnimationFrame for smoother animations
            requestAnimationFrame(() => {
                setTimeout(() => {
                    entry.target.classList.add('visible');
                }, index * 50); // Reduced stagger delay
            });
            observer.unobserve(entry.target);
        }
    });
}, observerOptions);

// Observe all animated elements
const animatedElements = document.querySelectorAll('.feature-card, .benefit-item, .pricing-card, .contact-item, .section-header');
animatedElements.forEach((el, index) => {
    el.classList.add('fade-in');
    observer.observe(el);
});

// Stagger animation for feature cards
document.querySelectorAll('.feature-card').forEach((card, index) => {
    card.style.transitionDelay = `${index * 0.1}s`;
});

// Stagger animation for pricing cards
document.querySelectorAll('.pricing-card').forEach((card, index) => {
    card.style.transitionDelay = `${index * 0.1}s`;
});

// Enhanced Counter Animation for Stats
const animateCounter = (element, target, suffix = '') => {
    let current = 0;
    const duration = 2000;
    const increment = target / (duration / 16);
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            element.textContent = target + suffix;
            clearInterval(timer);
            // Add a bounce effect
            element.style.transform = 'scale(1.2)';
            setTimeout(() => {
                element.style.transform = 'scale(1)';
                element.style.transition = 'transform 0.3s ease';
            }, 100);
        } else {
            element.textContent = Math.floor(current) + suffix;
        }
    }, 16);
};

// Animate stats when they come into view
const statNumbers = document.querySelectorAll('.stat-number');
statNumbers.forEach(stat => {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const text = entry.target.textContent;
                // Extract number and suffix
                const match = text.match(/(\d+)(.*)/);
                if (match) {
                    const number = parseInt(match[1]);
                    const suffix = match[2];
                    animateCounter(entry.target, number, suffix);
                    observer.unobserve(entry.target);
                }
            }
        });
    }, { threshold: 0.5 });
    
    observer.observe(stat);
});

// Optimized Parallax effect for hero section using requestAnimationFrame
let ticking = false;
const heroImage = document.querySelector('.hero-image');

function updateParallax() {
    const scrolled = window.pageYOffset;
    if (heroImage && scrolled < window.innerHeight) {
        const translateY = scrolled * 0.3;
        const opacity = 1 - (scrolled / window.innerHeight) * 0.3;
        heroImage.style.transform = `translate3d(0, ${translateY}px, 0)`;
        heroImage.style.opacity = opacity;
    }
    ticking = false;
}

window.addEventListener('scroll', () => {
    if (!ticking) {
        window.requestAnimationFrame(updateParallax);
        ticking = true;
    }
});

// Optimized scroll progress indicator using requestAnimationFrame
const createScrollProgress = () => {
    const progressBar = document.createElement('div');
    progressBar.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 0%;
        height: 3px;
        background: linear-gradient(90deg, var(--primary), var(--primary-dark));
        z-index: 10000;
        transform: translate3d(0, 0, 0);
        will-change: transform;
    `;
    document.body.appendChild(progressBar);
    
    let progressTicking = false;
    function updateProgress() {
        const windowHeight = document.documentElement.scrollHeight - window.innerHeight;
        const scrolled = (window.pageYOffset / windowHeight) * 100;
        progressBar.style.transform = `scaleX(${scrolled / 100})`;
        progressBar.style.transformOrigin = 'left';
        progressTicking = false;
    }
    
    window.addEventListener('scroll', () => {
        if (!progressTicking) {
            window.requestAnimationFrame(updateProgress);
            progressTicking = true;
        }
    });
};

createScrollProgress();

// Add ripple effect to buttons
document.querySelectorAll('.btn').forEach(button => {
    button.addEventListener('click', function(e) {
        const ripple = document.createElement('span');
        const rect = this.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const x = e.clientX - rect.left - size / 2;
        const y = e.clientY - rect.top - size / 2;
        
        ripple.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            left: ${x}px;
            top: ${y}px;
            transform: scale(0);
            animation: ripple 0.6s ease-out;
            pointer-events: none;
        `;
        
        this.style.position = 'relative';
        this.style.overflow = 'hidden';
        this.appendChild(ripple);
        
        setTimeout(() => ripple.remove(), 600);
    });
});

// Add ripple animation
const style = document.createElement('style');
style.textContent = `
    @keyframes ripple {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

