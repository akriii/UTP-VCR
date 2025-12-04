<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>User Portal | UTP Vendor Code Requisition</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* EXACT CSS FROM ADMIN LANDING PAGE FOR CONSISTENCY */
:root {
  --purple-dark: #2e3a7a;
  --purple-mid: #5a66a8;
  --purple-light: #f4effa;
  --mimos-magenta: rgb(220, 167, 67);
  --green-soft: #d4f1e1;
  --green-hover: #bfe3ce;
  --text-main: #4e4d7d;
  --text-secondary: #595790;
  --neutral-bg: #fbf8fd;
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body {
  font-family: 'Segoe UI', sans-serif;
  background: linear-gradient(to bottom, var(--neutral-bg), white);
  color: var(--text-main);
  line-height: 1.6;
}

a { text-decoration: none; color: inherit; }

/* Navbar */
.navbar {
  width: 100%;
  background-color: var(--purple-dark); /* Changed to match Admin Navbar */
  border-bottom: 1px solid #1a224a;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
  position: fixed;
  top: 0;
  left: 0;
  z-index: 1000;
}

.navbar-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 1rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.logo-container {
    display: flex;
    align-items: center;
    gap: 15px;
}

.logo {
  height: 50px; 
  width: auto;
  transition: transform 0.3s ease;
}
.logo:hover { transform: scale(1.05); }

.logo-text {
    font-size: 1.5rem;
    font-weight: 800;
    color: white;
    letter-spacing: 1px;
}

.login-btn {
  background-color: var(--mimos-magenta); /* Changed to match Admin Button */
  color: white;
  padding: 0.75rem 1.5rem;
  border-radius: 0.5rem;
  font-size: 1rem;
  font-weight: 600;
  transition: all 0.3s ease;
  border: none;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.login-btn:hover {
  background-color: #c49235;
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}

/* Hero Section */
.hero {
  padding: 10rem 1.5rem 6rem;
  background: linear-gradient(to bottom, var(--neutral-bg), white);
  text-align: center;
}

.hero-container {
  max-width: 1000px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 2rem;
  align-items: center;
}

.hero-text h1 {
  font-size: 3rem;
  font-weight: 800;
  margin-bottom: 0.5rem;
  color: var(--purple-dark);
  line-height: 1.2;
}

.hero-text h2 {
  font-size: 1.8rem;
  font-weight: 600;
  margin-bottom: 1.5rem;
  color: var(--mimos-magenta);
  letter-spacing: 1px;
}

.hero-text p {
  font-size: 1.2rem;
  color: var(--text-secondary);
  max-width: 800px;
  margin: 0 auto;
}

/* Features Section */
.features {
  background-color: white;
  padding: 5rem 1.5rem;
  position: relative;
}

.features-container {
  max-width: 1200px;
  margin: 0 auto;
}

.section-title {
  text-align: center;
  margin-bottom: 4rem;
}

.section-title h2 {
  font-size: 2.4rem;
  font-weight: 700;
  color: var(--purple-dark);
  margin-bottom: 1rem;
  position: relative;
  display: inline-block;
}

.section-title h2::after {
  content: '';
  position: absolute;
  bottom: -10px;
  left: 50%;
  transform: translateX(-50%);
  width: 60px;
  height: 4px;
  background-color: var(--mimos-magenta);
  border-radius: 2px;
}

.section-title p {
  color: var(--text-secondary);
  font-size: 1.1rem;
  margin-top: 1rem;
}

.feature-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 2.5rem;
}

@media (min-width: 768px) {
  .feature-grid {
    grid-template-columns: repeat(3, 1fr);
    gap: 2rem;
  }
}

.feature-card {
  background: white;
  padding: 3rem 2rem;
  border-radius: 1rem;
  box-shadow: 0 10px 30px rgba(46, 58, 122, 0.05);
  border: 1px solid rgba(229, 231, 235, 0.5);
  text-align: center;
  transition: all 0.4s ease;
  position: relative;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.feature-card:hover {
  transform: translateY(-10px);
  box-shadow: 0 20px 40px rgba(46, 58, 122, 0.1);
  border-color: var(--purple-light);
}

.feature-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 5px;
  background: var(--purple-mid);
  transform: scaleX(0);
  transition: transform 0.4s ease;
  transform-origin: left;
}

.feature-card:hover::before {
  transform: scaleX(1);
}

.icon-wrapper {
  width: 70px;
  height: 70px;
  background-color: var(--purple-light);
  color: var(--purple-dark);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.8rem;
  margin-bottom: 1.5rem;
  transition: all 0.3s ease;
}

.feature-card:hover .icon-wrapper {
  background-color: var(--purple-dark);
  color: white;
}

.feature-card h3 {
  font-size: 1.3rem;
  color: var(--text-main);
  margin-bottom: 1rem;
  font-weight: 700;
}

.feature-card p {
  font-size: 0.95rem;
  color: var(--text-secondary);
  line-height: 1.6;
}

/* CTA Section */
.cta {
  background-color: var(--purple-dark); /* Matched with Admin */
  color: white;
  text-align: center;
  padding: 5rem 1rem;
  position: relative;
  overflow: hidden;
}

.cta-container {
  max-width: 800px;
  margin: 0 auto;
  position: relative;
  z-index: 1;
}

.cta h2 {
  font-size: 2.2rem;
  font-weight: 700;
  margin-bottom: 1rem;
}

.cta p {
  font-size: 1.1rem;
  margin-bottom: 0.5rem;
  opacity: 0.9;
}

/* Footer */
footer {
  background-color: var(--purple-light);
  padding: 3rem 1rem;
  text-align: center;
}

.footer-container { max-width: 1200px; margin: 0 auto; }

footer img {
  height: 60px;
  width: auto;
  margin-bottom: 1rem;
}

.copyright {
  color: var(--text-secondary);
  font-size: 0.9rem;
  margin-top: 1rem;
}
</style>
</head>
<body>

  <!-- Navbar -->
  <nav class="navbar">
    <div class="navbar-container">
        <div class="logo-container">
            <img src="image/utp_logo.png" alt="UTP Logo" class="logo" />
            <div class="logo-text">UTP VCR</div>
        </div>
      <a href="user/login.php" class="login-btn">Login Now</a>
    </div>
  </nav>

  <!-- Hero Section -->
  <section class="hero">
    <div class="hero-container">
      <div class="hero-text">
        <h1>UTP VCR</h1>
        <h2>Secure Document Verification & Code Requisition</h2>
        <p>The UTP VCR (Vendor Code Requisition) website is a centralized platform designed to facilitate the process of customers issuing an official Vendor Code.</p>
      </div>
    </div>
  </section>

  <!-- Features Section -->
  <section class="features">
    <div class="features-container">
      
      <div class="section-title">
        <h2>Who is this for?</h2>
        <p>Determine your category to understand the registration requirements.</p>
      </div>

      <div class="feature-grid">
        
        <!-- Student Card -->
        <div class="feature-card">
          <div class="icon-wrapper">
            <i class="fas fa-user-graduate"></i>
          </div>
          <h3>Student</h3>
          <p>FYP students required to submit their FYP Form 02, IC picture, bank statement, and declaration form to complete their requisition.</p>
        </div>

        <!-- External Appointment Card -->
        <div class="feature-card">
          <div class="icon-wrapper">
            <i class="fas fa-briefcase"></i>
          </div>
          <h3>External Appointment</h3>
          <p>Individuals requiring an internal referral to register. Must submit an appointment letter along with standard identification documents.</p>
        </div>

        <!-- Company Card -->
        <div class="feature-card">
          <div class="icon-wrapper">
            <i class="fas fa-building"></i>
          </div>
          <h3>Company</h3>
          <p>Corporate entities registering for vendor status. Requires submission of SSM details and financial documents for verification.</p>
        </div>

      </div>
    </div>
  </section>

  <!-- CTA Section -->
  <section class="cta">
    <div class="cta-container">
      <h2>Universiti Teknologi PETRONAS</h2>
      <p>Streamlining the vendor requisition process through secure digital verification.</p>
    </div>
  </section>

  <!-- Footer -->
  <footer>
    <div class="footer-container">
      <img src="image/utp_logo.png" alt="UTP Footer Logo" />
      <p class="copyright">© 2025 UTP VCR. All rights reserved.</p>
    </div>
  </footer>

</body>
</html>