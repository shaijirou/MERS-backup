/**
 * Forgot Password Form Handler with EmailJS Integration
 * Follows the same pattern as contactus-gh9cf.js and try-W2CTy.js
 */

const resetRequests = []
const emailjs = window.emailjs // Declare the emailjs variable

function handleForgotPassword() {
  const form = document.getElementById("resetRequestForm")
  const btn = document.getElementById("sendCodeBtn")

  if (!form) return // Exit if form doesn't exist

  // Function to capture form data
  function captureResetData() {
    return {
      email: document.getElementById("email").value,
    }
  }

  // Adding event listener to form submission
  form.addEventListener("submit", (event) => {
    event.preventDefault()

    // Capture the reset request details
    const resetRequest = captureResetData()
    resetRequests.push(resetRequest)

    // Proceed to send reset code via EmailJS
    sendResetCode(btn, resetRequest.email)
  })
}

// Function to send reset code using EmailJS
function sendResetCode(btn, email) {
  const serviceID = "service_k9i98tr" // Replace with your EmailJS service ID
  const templateID = "template_f5ceyhw" // Replace with your EmailJS template ID for forgot password

  btn.textContent = "Sending..."
  btn.disabled = true

  // Generate a 6-digit reset code
  const resetCode = Math.floor(100000 + Math.random() * 900000).toString()

  // Email parameters
  const emailParams = {
    to_email: email,
    user_name: email.split("@")[0],
    reset_code: resetCode,
    reset_link: window.location.origin + "/forgot-password.php",
  }

  // Send email
  emailjs
    .send(serviceID, templateID, emailParams)
    .then(() => {
      btn.textContent = "Send Reset Code"
      btn.disabled = false

      // Store reset code in session via AJAX
      storeResetCode(email, resetCode)

      alert("Reset code sent successfully! Check your email.")
    })
    .catch((err) => {
      btn.textContent = "Send Reset Code"
      btn.disabled = false
      console.error("Error sending reset code:", err)
      alert("Failed to send reset code. Please try again later.")
    })
}

// Function to store reset code in session
function storeResetCode(email, resetCode) {
  fetch("api/store-reset-code.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      email: email,
      reset_code: resetCode,
    }),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        console.log("Reset code stored successfully")
      } else {
        console.error("Error storing reset code:", data.message)
      }
    })
    .catch((error) => {
      console.error("Error:", error)
    })
}

// Initialize the form submission process
handleForgotPassword()
