        </div>
    </div>
    
    <!-- Bootstrap 5.3.3 JS with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery 3.7.1 -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Sidebar toggle functionality
            $('#toggleSidebar').on('click', function() {
                $('#sidebar-wrapper').toggleClass('collapsed');
                $('#page-content-wrapper').toggleClass('expanded');
                
                // Store sidebar state in localStorage
                const isCollapsed = $('#sidebar-wrapper').hasClass('collapsed');
                localStorage.setItem('sidebarCollapsed', isCollapsed);
            });
            
            // Restore sidebar state from localStorage
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed');
            if (sidebarCollapsed === 'true') {
                $('#sidebar-wrapper').addClass('collapsed');
                $('#page-content-wrapper').addClass('expanded');
            }
            
            // Mobile sidebar toggle
            if ($(window).width() <= 768) {
                $('#toggleSidebar').on('click', function(e) {
                    e.stopPropagation();
                    $('#sidebar-wrapper').toggleClass('show');
                    $('#sidebarOverlay').toggleClass('show');
                });
                
                // Close sidebar when clicking overlay
                $('#sidebarOverlay').on('click', function() {
                    $('#sidebar-wrapper').removeClass('show');
                    $('#sidebarOverlay').removeClass('show');
                });
                
                // Close sidebar when clicking outside on mobile
                $(document).on('click', function(e) {
                    if (!$(e.target).closest('#sidebar-wrapper, #toggleSidebar').length) {
                        $('#sidebar-wrapper').removeClass('show');
                        $('#sidebarOverlay').removeClass('show');
                    }
                });
            }
            
            // User dropdown toggle
            $('#userDropdownToggle').on('click', function(e) {
                e.stopPropagation();
                $('#userDropdownMenu').toggleClass('show');
            });
            
            // Close dropdown when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.user-dropdown').length) {
                    $('#userDropdownMenu').removeClass('show');
                }
            });
            
            // Handle window resize
            $(window).on('resize', function() {
                if ($(window).width() > 768) {
                    $('#sidebar-wrapper').removeClass('show');
                    $('#sidebarOverlay').removeClass('show');
                }
            });
            
            // Auto-hide alerts after 5 seconds
            $('.alert').each(function() {
                const alert = $(this);
                setTimeout(function() {
                    alert.fadeOut('slow');
                }, 5000);
            });
            
            // Add loading state to buttons on form submit
            $('form').on('submit', function() {
                const submitBtn = $(this).find('button[type="submit"]');
                const originalText = submitBtn.html();
                
                submitBtn.prop('disabled', true);
                submitBtn.html('<span class="loading"></span> Processing...');
                
                // Re-enable button after 10 seconds (fallback)
                setTimeout(function() {
                    submitBtn.prop('disabled', false);
                    submitBtn.html(originalText);
                }, 10000);
            });
            
            // Confirm delete actions
            $('.btn-delete, .delete-btn').on('click', function(e) {
                e.preventDefault();
                const href = $(this).attr('href');
                const itemName = $(this).data('item') || 'this item';
                
                if (confirm(`Are you sure you want to delete ${itemName}? This action cannot be undone.`)) {
                    window.location.href = href;
                }
            });
            
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Initialize popovers
            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
            
            // Table row hover effect
            $('.table tbody tr').hover(
                function() {
                    $(this).addClass('table-hover-row');
                },
                function() {
                    $(this).removeClass('table-hover-row');
                }
            );
            
            // Search functionality for tables
            $('.table-search').on('keyup', function() {
                const value = $(this).val().toLowerCase();
                const table = $($(this).data('table'));
                
                table.find('tbody tr').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                });
            });
            
            // Auto-focus first input in modals
            $('.modal').on('shown.bs.modal', function() {
                $(this).find('input:first').focus();
            });
            
            // Prevent double form submission
            let formSubmitted = false;
            $('form').on('submit', function() {
                if (formSubmitted) {
                    return false;
                }
                formSubmitted = true;
                return true;
            });
        });
        
        // Global AJAX setup
        $.ajaxSetup({
            beforeSend: function(xhr, settings) {
                // Add CSRF token if available
                const token = $('meta[name="csrf-token"]').attr('content');
                if (token) {
                    xhr.setRequestHeader('X-CSRF-TOKEN', token);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                
                // Show user-friendly error message
                if (xhr.status === 403) {
                    alert('Access denied. Please check your permissions.');
                } else if (xhr.status === 404) {
                    alert('The requested resource was not found.');
                } else if (xhr.status === 500) {
                    alert('Server error occurred. Please try again later.');
                } else {
                    alert('An error occurred. Please try again.');
                }
            }
        });
        
        // Utility functions
        function showAlert(type, message, duration = 5000) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            
            $('.main-content').prepend(alertHtml);
            
            // Auto-hide after duration
            setTimeout(function() {
                $('.alert').first().fadeOut('slow', function() {
                    $(this).remove();
                });
            }, duration);
        }
        
        function formatNumber(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }
        
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }
        
        // Print functionality
        function printElement(elementId) {
            const element = document.getElementById(elementId);
            const printWindow = window.open('', '_blank');
            
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Print</title>
                        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
                        <style>
                            body { font-family: Arial, sans-serif; }
                            @media print {
                                .no-print { display: none !important; }
                                .page-break { page-break-before: always; }
                            }
                        </style>
                    </head>
                    <body>
                        ${element.innerHTML}
                    </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
            printWindow.close();
        }
    </script>
    
    <!-- Custom JavaScript for specific pages -->
    <?php if (isset($custom_js)): ?>
        <script>
            <?php echo $custom_js; ?>
        </script>
    <?php endif; ?>
    
    <!-- Page-specific JavaScript files -->
    <?php if (isset($js_files) && is_array($js_files)): ?>
        <?php foreach ($js_files as $js_file): ?>
            <script src="<?php echo BASE_URL; ?>/assets/js/<?php echo $js_file; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>