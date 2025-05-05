const togglePassword = document.getElementById("togglePassword");
const passwordInput = document.getElementById("password");

togglePassword.addEventListener("click", function () {
  if (passwordInput.type === "password") {
    passwordInput.type = "text";
    togglePassword.src = "images/eye-off-svgrepo-com.svg";
  } else {
    passwordInput.type = "password";
    togglePassword.src = "images/eye-show-svgrepo-com.svg";
  }
});

//Start Background
document.addEventListener("DOMContentLoaded", () => {
  const container = document.getElementById("starsContainer");
  const containerWidth = container.offsetWidth;
  const containerHeight = container.offsetHeight;

  // Create stars
  const starCount = Math.floor((containerWidth * containerHeight) / 10000);

  for (let i = 0; i < starCount; i++) {
    createStar(container);
  }

  // Create shooting stars with consistent timing
  createShootingStarsWithTiming(container);
});

function createStar(container) {
  const star = document.createElement("div");
  star.classList.add("star");

  // Random position
  const x = Math.random() * 100;
  const y = Math.random() * 100;

  // Random size
  const size = Math.random() * 1.5 + 0.5;

  // Random animation duration for blinking
  const duration = Math.random() * 4 + 2;

  // Blinking effect
  const minOpacity = Math.random() * 0.2;
  const maxOpacity = Math.min(minOpacity + 0.7, 1);

  star.style.left = `${x}%`;
  star.style.top = `${y}%`;
  star.style.width = `${size}px`;
  star.style.height = `${size}px`;
  star.style.setProperty("--duration", `${duration}s`);
  star.style.setProperty("--min-opacity", minOpacity);
  star.style.setProperty("--max-opacity", maxOpacity);

  // Delay animation start randomly
  star.style.animationDelay = `${Math.random() * duration}s`;

  container.appendChild(star);
}

function createShootingStarsWithTiming(container) {
  // Only have 2 shooting stars cycling
  const shootingStarCount = 2;
  let activeStar = 0;

  // Create initial shooting star
  createShootingStar(container, 0);

  // Create subsequent shooting stars with consistent 5-6 second intervals
  setInterval(() => {
    activeStar = (activeStar + 1) % shootingStarCount;
    createShootingStar(container, activeStar);
  }, 5500); // 5.5 seconds between each star
}

function createShootingStar(container, index) {
  // Check if shooting star already exists with this index
  let shootingStar = container.querySelector(`.shooting-star-${index}`);

  // Create new shooting star if it doesn't exist
  if (!shootingStar) {
    shootingStar = document.createElement("div");
    shootingStar.classList.add("shooting-star", `shooting-star-${index}`);
    container.appendChild(shootingStar);
  }

  // Random parameters, but more constrained
  const size = Math.random() * 100 + 70; // Length
  const width = Math.random() * 1.5 + 0.8; // Width/thickness
  const angle = Math.random() * 30 + 60; // Between 60 and 90 degrees (downward angle)
  const travelDistance = Math.random() * 80 + 100; // How far it travels
  const duration = Math.random() * 3 + 2; // Slower animation: 2-5 seconds

  // Starting position (always start from top)
  const startX = Math.random() * 80 + 10; // Random horizontal position (10% to 90%)
  const startY = -5; // Start above the viewport

  // Set properties
  shootingStar.style.width = `${size}px`;
  shootingStar.style.height = `${width}px`;
  shootingStar.style.left = `${startX}%`;
  shootingStar.style.top = `${startY}%`;
  shootingStar.style.setProperty("--angle", `${angle}deg`);
  shootingStar.style.setProperty("--travel-distance", `${travelDistance}vh`);
  shootingStar.style.setProperty("--shoot-duration", `${duration}s`);

  // Reset animation by removing and re-adding
  shootingStar.style.animation = "none";
  setTimeout(() => {
    shootingStar.style.animation = `shoot ${duration}s linear`;
  }, 10);
}
//End Background
