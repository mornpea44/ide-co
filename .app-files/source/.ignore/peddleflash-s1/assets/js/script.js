// Peddleflash - Scenario 1 Test Script
// If you can see the message below change in the status box, the JS
// file loaded correctly (the asset path was resolved successfully).

document.addEventListener("DOMContentLoaded", function () {
    var box = document.getElementById("status-box");
    if (box) {
        box.textContent = "JS loaded successfully! Assets are resolving correctly.";
    }
});
