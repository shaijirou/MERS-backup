function editCenter(centerId) {
  fetch(`get_center_details.php?id=${centerId}`)
    .then((response) => response.json())
    .then((data) => {
      if (data.error) {
        alert("Error: " + data.error)
        return
      }

      // Populate form fields
      document.getElementById("editCenterId").value = data.id
      document.getElementById("editCenterName").value = data.name
      document.getElementById("editCenterAddress").value = data.address
      document.getElementById("editCenterCapacity").value = data.capacity
      document.getElementById("editCurrentOccupancy").value = data.current_occupancy
      document.getElementById("editContactPerson").value = data.contact_person || ""
      document.getElementById("editContactNumber").value = data.contact_number || ""
      document.getElementById("editLatitude").value = data.latitude
      document.getElementById("editLongitude").value = data.longitude

      const barangaySelect = document.getElementById("editBarangay")
      barangaySelect.value = data.barangay_name

      // Set status
      const statusSelect = document.getElementById("editStatus")
      statusSelect.value = data.status

      // Handle facilities checkboxes
      const facilities = data.facilities || []
      document.querySelectorAll('#editCenterModal input[name="facilities[]"]').forEach((checkbox) => {
        checkbox.checked = facilities.includes(checkbox.value)
      })

      // Show modal
      document.getElementById("editCenterModal").style.display = "block"
    })
    .catch((error) => {
      console.error("Error:", error)
      alert("Error loading center details")
    })
}

function viewCenter(centerId) {
  fetch(`get_center_details.php?id=${centerId}`)
    .then((response) => response.json())
    .then((data) => {
      if (data.error) {
        alert("Error: " + data.error)
        return
      }

      // Populate view modal
      document.getElementById("viewCenterName").textContent = data.name
      document.getElementById("viewCenterAddress").textContent = data.address
      document.getElementById("viewCenterBarangay").textContent = data.barangay_name
      document.getElementById("viewCenterCapacity").textContent = data.capacity
      document.getElementById("viewCurrentOccupancy").textContent = data.current_occupancy
      document.getElementById("viewContactPerson").textContent = data.contact_person || "N/A"
      document.getElementById("viewContactNumber").textContent = data.contact_number || "N/A"
      document.getElementById("viewLatitude").textContent = data.latitude
      document.getElementById("viewLongitude").textContent = data.longitude

      const facilitiesList = document.getElementById("viewFacilities")
      if (data.facilities && data.facilities.length > 0) {
        facilitiesList.innerHTML = data.facilities
          .map((facility) => `<span class="facility-tag">${facility.replace("_", " ").toUpperCase()}</span>`)
          .join(" ")
      } else {
        facilitiesList.textContent = "No facilities listed"
      }

      // Set status with appropriate styling
      const statusElement = document.getElementById("viewStatus")
      statusElement.textContent = data.status.toUpperCase()
      statusElement.className = `status-badge status-${data.status}`

      // Calculate and display occupancy percentage
      const occupancyRate = data.capacity > 0 ? (data.current_occupancy / data.capacity) * 100 : 0
      const progressBar = document.getElementById("viewOccupancyProgress")
      const progressText = document.getElementById("viewOccupancyText")

      progressBar.style.width = occupancyRate + "%"
      progressText.textContent = `${data.current_occupancy} / ${data.capacity} (${occupancyRate.toFixed(1)}%)`

      // Color code the progress bar
      if (occupancyRate >= 90) {
        progressBar.className = "progress-bar progress-danger"
      } else if (occupancyRate >= 70) {
        progressBar.className = "progress-bar progress-warning"
      } else {
        progressBar.className = "progress-bar progress-success"
      }

      // Set edit button to open edit modal
      document.getElementById("editFromViewBtn").onclick = () => {
        document.getElementById("viewCenterModal").style.display = "none"
        editCenter(centerId)
      }

      // Format dates
      const createdDate = new Date(data.created_at)
      const updatedDate = new Date(data.updated_at)
      document.getElementById("viewCreatedAt").textContent =
        createdDate.toLocaleDateString() + " " + createdDate.toLocaleTimeString()
      document.getElementById("viewUpdatedAt").textContent =
        updatedDate.toLocaleDateString() + " " + updatedDate.toLocaleTimeString()

      // Show modal
      document.getElementById("viewCenterModal").style.display = "block"
    })
    .catch((error) => {
      console.error("Error:", error)
      alert("Error loading center details")
    })
}
