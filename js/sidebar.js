/**
 * Sidebar dropdown functionality for Voltech2
 * Ensures consistent behavior across all pages
 */
$(document).ready(function() {
    // Traditional Bootstrap dropdown behavior - much more reliable
    $('.dropdown-toggle').dropdown();
    
    // Keep dropdowns open when clicking on them
    $(document).on('click', '.dropdown-menu', function(e) {
        e.stopPropagation();
    });
    
    // Toggle sidebar functionality
    $("#menu-toggle").click(function(e) {
        e.preventDefault();
        $("#wrapper").toggleClass("toggled");
    });
    
    // Activate feather icons
    feather.replace();
});
