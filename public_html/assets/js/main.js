// main.js

document.addEventListener("DOMContentLoaded", function() {
    // Smooth scrolling for anchor links
    const links = document.querySelectorAll('a[href^="#"]');
    links.forEach(link => {
      link.addEventListener('click', function(e) {
        e.preventDefault();
        const targetID = this.getAttribute("href").substring(1);
        const targetSection = document.getElementById(targetID);
        if (targetSection) {
          targetSection.scrollIntoView({
            behavior: "smooth",
            block: "start"
          });
        }
      });
    });
  
    // Mobile menu toggle (if applicable)
    // Uncomment the following if you add a hamburger menu element in your HTML.
    // const hamburger = document.getElementById("hamburger");
    // const navLinks = document.querySelector(".nav-links");
    // if (hamburger) {
    //   hamburger.addEventListener("click", function() {
    //     navLinks.classList.toggle("active");
    //   });
    // }
  });
  