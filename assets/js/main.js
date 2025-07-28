// Main JavaScript file for Agoncillo Disaster Alert System

// Import Bootstrap
const bootstrap = window.bootstrap

// Global variables
let map
let userLocation = null
let alertSound = null

// Initialize application
document.addEventListener("DOMContentLoaded", () => {
  initializeApp()
  setupEventListeners()
  checkForEmergencyAlerts()
})

// Initialize application
function initializeApp() {
  // Initialize tooltips
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  var tooltipList = tooltipTriggerList.map((tooltipTriggerEl) => new bootstrap.Tooltip(tooltipTriggerEl))

  // Initialize popovers
  var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
  var popoverList = popoverTriggerList.map((popoverTriggerEl) => new bootstrap.Popover(popoverTriggerEl))

  // Initialize alert sound
  alertSound = new Audio("assets/sounds/alert.mp3")

  // Set up auto-refresh for alerts
  setInterval(checkForNewAlerts, 30000) // Check every 30 seconds

  // Initialize geolocation if supported
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (position) => {
        userLocation = {
          lat: position.coords.latitude,
          lng: position.coords.longitude,
        }
        console.log("User location obtained:", userLocation)
      },
      (error) => {
        console.log("Geolocation error:", error.message)
      },
    )
  }
}

// Setup event listeners
function setupEventListeners() {
  // Form validation
  const forms = document.querySelectorAll(".needs-validation")
  Array.prototype.slice.call(forms).forEach((form) => {
    form.addEventListener(
      "submit",
      (event) => {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        }
        form.classList.add("was-validated")
      },
      false,
    )
  })

  // File upload preview
  const fileInputs = document.querySelectorAll('input[type="file"]')
  fileInputs.forEach((input) => {
    input.addEventListener("change", (e) => {
      previewFiles(e.target)
    })
  })

  // Auto-hide alerts
  const alerts = document.querySelectorAll(".alert:not(.alert-permanent)")
  alerts.forEach((alert) => {
    setTimeout(() => {
      if (alert && alert.parentNode) {
        alert.style.opacity = "0"
        setTimeout(() => {
          alert.remove()
        }, 300)
      }
    }, 5000)
  })

  // Smooth scrolling for anchor links
  const anchorLinks = document.querySelectorAll('a[href^="#"]')
  anchorLinks.forEach((link) => {
    link.addEventListener("click", function (e) {
      e.preventDefault()
      const target = document.querySelector(this.getAttribute("href"))
      if (target) {
        target.scrollIntoView({
          behavior: "smooth",
          block: "start",
        })
      }
    })
  })

  // Search functionality
  const searchInputs = document.querySelectorAll(".search-input")
  searchInputs.forEach((input) => {
    input.addEventListener("input", (e) => {
      debounce(performSearch, 300)(e.target.value, e.target.dataset.target)
    })
  })

  // Copy to clipboard functionality
  const copyButtons = document.querySelectorAll(".copy-btn")
  copyButtons.forEach((btn) => {
    btn.addEventListener("click", (e) => {
      copyToClipboard(e.target.dataset.copy)
    })
  })
}

// Check for emergency alerts
function checkForEmergencyAlerts() {
  // This would typically make an AJAX call to check for new emergency alerts
  // For now, we'll simulate checking localStorage or a data attribute
  const emergencyAlert = document.querySelector(".emergency-alert")
  if (emergencyAlert) {
    playAlertSound()
    showNotification("Emergency Alert", emergencyAlert.textContent, "error")
  }
}

// Check for new alerts (AJAX call)
function checkForNewAlerts() {
  // This would make an AJAX call to the server to check for new alerts
  fetch("api/check-alerts.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.newAlerts && data.newAlerts.length > 0) {
        data.newAlerts.forEach((alert) => {
          showNotification(alert.title, alert.message, alert.type)
          if (alert.type === "emergency") {
            playAlertSound()
          }
        })
      }
    })
    .catch((error) => {
      console.error("Error checking for alerts:", error)
    })
}

// Play alert sound
function playAlertSound() {
  if (alertSound) {
    alertSound.play().catch((error) => {
      console.log("Could not play alert sound:", error)
    })
  }
}

// Show notification
function showNotification(title, message, type = "info") {
  // Check if browser supports notifications
  if ("Notification" in window) {
    if (Notification.permission === "granted") {
      new Notification(title, {
        body: message,
        icon: "assets/img/logo.png",
        tag: "disaster-alert",
      })
    } else if (Notification.permission !== "denied") {
      Notification.requestPermission().then((permission) => {
        if (permission === "granted") {
          new Notification(title, {
            body: message,
            icon: "assets/img/logo.png",
            tag: "disaster-alert",
          })
        }
      })
    }
  }

  // Also show in-app notification
  showInAppNotification(title, message, type)
}

// Show in-app notification
function showInAppNotification(title, message, type = "info") {
  const notificationContainer = document.getElementById("notification-container") || createNotificationContainer()

  const notification = document.createElement("div")
  notification.className = `alert alert-${type} alert-dismissible fade show notification-toast`
  notification.innerHTML = `
        <strong>${title}</strong> ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `

  notificationContainer.appendChild(notification)

  // Auto-remove after 5 seconds
  setTimeout(() => {
    if (notification.parentNode) {
      notification.remove()
    }
  }, 5000)
}

// Create notification container
function createNotificationContainer() {
  const container = document.createElement("div")
  container.id = "notification-container"
  container.className = "position-fixed top-0 end-0 p-3"
  container.style.zIndex = "9999"
  document.body.appendChild(container)
  return container
}

// File preview functionality
function previewFiles(input) {
  const files = input.files
  const previewContainer = input.parentNode.querySelector(".file-preview") || createPreviewContainer(input)

  previewContainer.innerHTML = ""

  Array.from(files).forEach((file) => {
    const reader = new FileReader()
    reader.onload = (e) => {
      const preview = document.createElement("div")
      preview.className = "file-preview-item"

      if (file.type.startsWith("image/")) {
        preview.innerHTML = `
                    <img src="${e.target.result}" alt="Preview" class="img-thumbnail" style="max-width: 100px; max-height: 100px;">
                    <small class="d-block text-muted">${file.name}</small>
                `
      } else {
        preview.innerHTML = `
                    <div class="file-icon">
                        <i class="bi bi-file-earmark"></i>
                    </div>
                    <small class="d-block text-muted">${file.name}</small>
                `
      }

      previewContainer.appendChild(preview)
    }
    reader.readAsDataURL(file)
  })
}

// Create preview container
function createPreviewContainer(input) {
  const container = document.createElement("div")
  container.className = "file-preview d-flex flex-wrap gap-2 mt-2"
  input.parentNode.appendChild(container)
  return container
}

// Search functionality
function performSearch(query, target) {
  const targetElement = document.querySelector(target)
  if (!targetElement) return

  const searchableItems = targetElement.querySelectorAll("[data-searchable]")

  searchableItems.forEach((item) => {
    const text = item.textContent.toLowerCase()
    const matches = text.includes(query.toLowerCase())

    if (matches || query === "") {
      item.style.display = ""
      item.classList.remove("search-hidden")
    } else {
      item.style.display = "none"
      item.classList.add("search-hidden")
    }
  })

  // Show "no results" message if needed
  const visibleItems = targetElement.querySelectorAll("[data-searchable]:not(.search-hidden)")
  let noResultsMsg = targetElement.querySelector(".no-results-message")

  if (visibleItems.length === 0 && query !== "") {
    if (!noResultsMsg) {
      noResultsMsg = document.createElement("div")
      noResultsMsg.className = "no-results-message text-center text-muted py-4"
      noResultsMsg.innerHTML = '<i class="bi bi-search"></i><br>No results found'
      targetElement.appendChild(noResultsMsg)
    }
    noResultsMsg.style.display = "block"
  } else if (noResultsMsg) {
    noResultsMsg.style.display = "none"
  }
}

// Copy to clipboard
function copyToClipboard(text) {
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text).then(() => {
      showInAppNotification("Copied!", "Text copied to clipboard", "success")
    })
  } else {
    // Fallback for older browsers
    const textArea = document.createElement("textarea")
    textArea.value = text
    document.body.appendChild(textArea)
    textArea.select()
    document.execCommand("copy")
    document.body.removeChild(textArea)
    showInAppNotification("Copied!", "Text copied to clipboard", "success")
  }
}

// Debounce function
function debounce(func, wait) {
  let timeout
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout)
      func(...args)
    }
    clearTimeout(timeout)
    timeout = setTimeout(later, wait)
  }
}

// Format date/time
function formatDateTime(dateString) {
  const date = new Date(dateString)
  return date.toLocaleDateString() + " " + date.toLocaleTimeString()
}

// Time ago function
function timeAgo(dateString) {
  const date = new Date(dateString)
  const now = new Date()
  const diffInSeconds = Math.floor((now - date) / 1000)

  if (diffInSeconds < 60) return "just now"
  if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + " minutes ago"
  if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + " hours ago"
  if (diffInSeconds < 2592000) return Math.floor(diffInSeconds / 86400) + " days ago"

  return date.toLocaleDateString()
}

// Validate form data
function validateForm(form) {
  const requiredFields = form.querySelectorAll("[required]")
  let isValid = true

  requiredFields.forEach((field) => {
    if (!field.value.trim()) {
      field.classList.add("is-invalid")
      isValid = false
    } else {
      field.classList.remove("is-invalid")
    }
  })

  return isValid
}

// Show loading state
function showLoading(element, text = "Loading...") {
  const originalContent = element.innerHTML
  element.innerHTML = `
        <span class="spinner-border spinner-border-sm me-2" role="status"></span>
        ${text}
    `
  element.disabled = true
  element.dataset.originalContent = originalContent
}

// Hide loading state
function hideLoading(element) {
  if (element.dataset.originalContent) {
    element.innerHTML = element.dataset.originalContent
    element.disabled = false
    delete element.dataset.originalContent
  }
}

// Initialize map (placeholder for future map integration)
function initializeMap(containerId, options = {}) {
  const container = document.getElementById(containerId)
  if (!container) return

  // This would initialize a real map (Google Maps, Leaflet, etc.)
  console.log("Map would be initialized here with options:", options)

  // For now, just add a placeholder
  container.innerHTML = `
        <div class="map-placeholder d-flex align-items-center justify-content-center bg-light" style="height: 400px;">
            <div class="text-center">
                <i class="bi bi-map fs-1 text-muted"></i>
                <p class="text-muted mt-2">Interactive map will be loaded here</p>
            </div>
        </div>
    `
}

// Export functions for use in other scripts
window.AgoncilloAlert = {
  showNotification,
  showInAppNotification,
  playAlertSound,
  formatDateTime,
  timeAgo,
  validateForm,
  showLoading,
  hideLoading,
  initializeMap,
  copyToClipboard,
}

// Service Worker registration (for offline functionality)
if ("serviceWorker" in navigator) {
  window.addEventListener("load", () => {
    navigator.serviceWorker
      .register("sw.js")
      .then((registration) => {
        console.log("ServiceWorker registration successful")
      })
      .catch((error) => {
        console.log("ServiceWorker registration failed")
      })
  })
}

// Handle online/offline status
window.addEventListener("online", () => {
  showInAppNotification("Connection Restored", "You are back online", "success")
})

window.addEventListener("offline", () => {
  showInAppNotification("Connection Lost", "You are currently offline", "warning")
})

// Request notification permission on page load
if ("Notification" in window && Notification.permission === "default") {
  setTimeout(() => {
    Notification.requestPermission()
  }, 2000)
}
