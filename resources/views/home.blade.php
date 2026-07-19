@extends('layouts.app')

@section('content')
<header class="top-nav">
    <a href="{{ route('home') }}" class="brand" aria-label="Ting Hao home">
        <img src="{{ asset('images/tinghao-logo-transparent.png') }}" alt="Ting Hao logo" width="160" height="64" decoding="async">
    </a>
    <nav class="main-links">
        <a href="#home" class="active">Home</a>
        <a href="#mission">About</a>
        <a href="#products">Products</a>
        <a href="#contact">Contact</a>
    </nav>
    <div class="nav-actions">
        
        <a href="{{ route('login') }}" class="btn btn-login">Admin Login</a>
    </div>
</header>

<section id="home" class="hero">
    <div class="hero-overlay"></div>
    <img src="https://unsplash.com/photos/X3XSSryTj3k/download?force=true&w=1800" alt="Bakery storefront" width="1800" height="1200" fetchpriority="high" decoding="async">
    <div class="hero-content">
        <h1>Ting Hao: Your Trusted<br>Baking Ingredient<br>Supplier</h1>
        <p>Nurturing the craft of artisanal baking with premium,
            source-verified ingredients delivered from our pantry to
            your professional kitchen.</p>
        <div class="hero-cta">
            <a href="#contact" class="btn btn-primary">Contact Us</a>
            <a href="#mission" class="btn btn-ghost">Our Story</a>
        </div>
    </div>
</section>

<section id="mission" class="mission section-wrap">
    <div class="mission-image-wrap">
        <img src="https://images.unsplash.com/photo-1483695028939-5bb13f8648b0?auto=format&fit=crop&w=900&q=80" alt="Kneading dough" width="900" height="600" loading="lazy" decoding="async">
        <div class="quote-card">Freshness is not an option, it is our heritage.</div>
    </div>
    <div class="mission-copy">
        <p class="eyebrow">OUR MISSION</p>
        <h2>Quality should never be a<br>barrier to craftsmanship.</h2>
        <p>At Ting Hao, we believe every baker deserves the finest foundation.
            We bridge the gap between global growers and local ovens, ensuring
            that whether you are a home hobbyist or a high-volume boulangerie,
            your ingredients are consistently fresh, ethical, and fairly priced.</p>
        <div class="stats">
            <div><strong>100%</strong><span>Organic Certified</span></div>
            <div><strong>24h</strong><span>Daily Freshness</span></div>
            <div><strong>500+</strong><span>Artisan Clients</span></div>
        </div>
    </div>
</section>

<section id="products" class="products">
    <div class="section-wrap">
        <h3>Curated Essentials</h3>
        <p class="section-sub">The pillars of your next masterpiece.</p>
        <div class="product-grid">
            <article class="card large" style="background-image:url('https://images.unsplash.com/photo-1608198093002-ad4e005484ec?auto=format&fit=crop&w=1100&q=80')">
                <div>
                    <h4>Premium Flours</h4>
                    <p>Stone-milled, high-protein varieties for perfect elasticity.</p>
                </div>
            </article>
            <article class="card" style="background-image:url('https://images.unsplash.com/photo-1587049352851-8d4e89133924?auto=format&fit=crop&w=900&q=80')">
                <div>
                    <h4>Natural Sugars</h4>
                    <p>Unrefined sweeteners for deep, caramel complexity.</p>
                </div>
            </article>
            <article class="card" style="background-image:url('https://unsplash.com/photos/Ns2aJ5OXKds/download?force=true&w=1800')">
                <div>
                    <h4>Professional Tools</h4>
                    <p>The hardware required for Michelin-star results.</p>
                </div>
            </article>
        </div>
    </div>
</section>

<section id="contact" class="contact section-wrap">
    <div>
        <h3>Visit the Pantry</h3>
        <p>Come experience the aroma of fresh grain and sample our seasonal specialty starches.</p>
        <ul class="contact-list">
            <li><strong>Our Location</strong><span>No.4, Jalan Loke Yew<br>Tanjung Malim,35900 Perak</span></li>
            <li><strong>Operating Hours</strong><span>Mon - Fri: 08:00 - 18:00<br>Sat: 09:00 - 15:00</span></li>
            <li><strong>Contact Us</strong><span>05-5497711<br>tinghaobakery@gmail.com</span></li>
        </ul>
    </div>
    <div class="map-box">
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d1990.7965203939684!2d101.5195496659005!3d3.679688391237933!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31cb877783198485%3A0xab0bfecedda9f008!2sTing%20Hao%20Bakery%20Ingredients%20shop!5e0!3m2!1sen!2smy!4v1779329726340!5m2!1sen!2smy" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
</section>



<footer class="footer">
    <div>Ting Hao<br><small>&copy; 2026 Ting Hao Artisanal Ledger. All rights reserved.</small></div>
    <div class="footer-links">
        <a href="#">Privacy Policy</a>
        <a href="#">Terms of Service</a>
        <a href="#">Shipping Info</a>
        <a href="#">Wholesale Inquiry</a>
    </div>
</footer>

<script>
    const sections = document.querySelectorAll('#home, #mission, #products, #contact');
    const navLinks = document.querySelectorAll('.main-links a');

    function setActiveNavLink() {
        let currentSection = 'home';

        sections.forEach((section) => {
            const sectionTop = section.offsetTop - 120;

            if (window.scrollY >= sectionTop) {
                currentSection = section.id;
            }
        });

        navLinks.forEach((link) => {
            link.classList.toggle('active', link.getAttribute('href') === `#${currentSection}`);
        });
    }

    window.addEventListener('scroll', setActiveNavLink);
    window.addEventListener('load', setActiveNavLink);
</script>
@endsection
