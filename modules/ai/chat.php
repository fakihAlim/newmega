<?php
/**
 * AI Chatbot Frontend - Tanya AI
 */
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('ai_chat');

$pageTitle = 'Tanya AI';
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => APP_URL . '/modules/dashboard/index.php'],
    ['label' => 'Tanya AI']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <div class="col-md-8 col-sm-12 mx-auto">
        <div class="card card-outline card-primary" style="border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
            <div class="card-header bg-white py-3" style="border-top-left-radius: 12px; border-top-right-radius: 12px;">
                <h3 class="card-title font-weight-bold text-primary">
                    <i class="fas fa-robot mr-2"></i> Asisten Proyek AI
                </h3>
            </div>
            
            <div class="card-body p-0">
                <!-- Chat Container -->
                <div id="chatContainer" style="height: 420px; overflow-y: auto; padding: 20px; background-color: #f8f9fa;">
                    
                    <!-- Welcome Message -->
                    <div class="d-flex mb-3 align-items-start">
                        <div class="bg-primary text-white p-3" style="border-radius: 15px; border-top-left-radius: 0; max-width: 80%; font-size: 14px;">
                            Halo, saya adalah Asisten AI untuk Sistem Procurement. Saya dapat menjawab pertanyaan terkait data proyek, material request, purchase order, supplier, dan stok barang secara ringkas.
                        </div>
                    </div>
                    
                </div>
            </div>
            
            <!-- Quick Chips Area -->
            <div class="card-footer bg-white border-top-0 px-3 py-2">
                <div class="d-flex flex-wrap gap-2" style="gap: 8px;">
                    <button type="button" class="btn btn-xs btn-outline-secondary quick-chip" data-text="Tampilkan Purchase Order yang masih pending">
                        <i class="fas fa-search mr-1"></i> PO Pending
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-secondary quick-chip" data-text="Tampilkan proyek yang belum berjalan">
                        <i class="fas fa-project-diagram mr-1"></i> Proyek Belum Jalan
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-secondary quick-chip" data-text="Apakah ada barang yang stoknya di bawah stok minimum?">
                        <i class="fas fa-exclamation-triangle mr-1"></i> Stok Menipis
                    </button>
                    <button type="button" class="btn btn-xs btn-outline-secondary quick-chip" data-text="Siapa supplier yang paling sering kita gunakan?">
                        <i class="fas fa-truck mr-1"></i> Top Supplier
                    </button>
                </div>
            </div>
            
            <!-- Input Area -->
            <div class="card-footer bg-white py-3" style="border-bottom-left-radius: 12px; border-bottom-right-radius: 12px;">
                <form id="chatForm">
                    <div class="input-group">
                        <input type="text" id="chatInput" class="form-control" placeholder="Tanyakan tentang proyek, PO, MR, atau stok..." style="border-radius: 20px; padding: 10px 15px; height: auto;" autocomplete="off" required>
                        <div class="input-group-append">
                            <button type="submit" class="btn btn-primary ml-2 px-4" style="border-radius: 20px;">
                                <i class="fas fa-paper-plane"></i> Kirim
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.quick-chip {
    border-radius: 15px;
    font-size: 11px;
    padding: 4px 10px;
    transition: all 0.2s ease;
}
.quick-chip:hover {
    background-color: #007bff;
    color: white;
    border-color: #007bff;
}
/* Scrollbar custom for chat area */
#chatContainer::-webkit-scrollbar {
    width: 6px;
}
#chatContainer::-webkit-scrollbar-track {
    background: #f1f1f1;
}
#chatContainer::-webkit-scrollbar-thumb {
    background: #ccc;
    border-radius: 3px;
}
#chatContainer::-webkit-scrollbar-thumb:hover {
    background: #aaa;
}
</style>

<?php
$extraJS = <<<'JS'
<script>
$(document).ready(function() {
    const chatContainer = $('#chatContainer');
    const chatForm = $('#chatForm');
    const chatInput = $('#chatInput');
    
    // Scroll chat container to bottom
    function scrollToBottom() {
        chatContainer.animate({ scrollTop: chatContainer[0].scrollHeight }, 300);
    }
    
    // Append a message bubble to the container
    function appendMessage(text, isUser = false) {
        let align = isUser ? 'justify-content-end' : 'justify-content-start';
        let bgClass = isUser ? 'bg-secondary text-white' : 'bg-primary text-white';
        let radius = isUser ? 'border-top-right-radius: 0;' : 'border-top-left-radius: 0;';
        
        // Convert Markdown links [text](url) to HTML links
        let formattedText = text;
        if (!isUser) {
            formattedText = formattedText.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" class="text-warning font-weight-bold" style="text-decoration: underline;">$1</a>');
        }
        formattedText = formattedText.replace(/\n/g, '<br>');
        
        let html = `
            <div class="d-flex mb-3 ${align}">
                <div class="${bgClass} p-3" style="border-radius: 15px; ${radius} max-width: 80%; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    ${formattedText}
                </div>
            </div>
        `;
        chatContainer.append(html);
        scrollToBottom();
    }
    
    // Show typing indicator
    function showTypingIndicator() {
        let html = `
            <div class="d-flex mb-3 justify-content-start" id="typingIndicator">
                <div class="bg-primary text-white p-3" style="border-radius: 15px; border-top-left-radius: 0; max-width: 80%; font-size: 14px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                    <span class="spinner-grow spinner-grow-sm" role="status" aria-hidden="true"></span>
                    <span class="spinner-grow spinner-grow-sm ml-1" role="status" aria-hidden="true"></span>
                    <span class="spinner-grow spinner-grow-sm ml-1" role="status" aria-hidden="true"></span>
                </div>
            </div>
        `;
        chatContainer.append(html);
        scrollToBottom();
    }
    
    // Remove typing indicator
    function removeTypingIndicator() {
        $('#typingIndicator').remove();
    }
    
    // Send message to backend
    function sendMessage(message) {
        if (!message) return;
        
        appendMessage(message, true);
        chatInput.val('');
        showTypingIndicator();
        
        $.ajax({
            url: 'ajax_chat.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ message: message }),
            success: function(response) {
                removeTypingIndicator();
                if (response.status === 'success') {
                    appendMessage(response.answer, false);
                } else {
                    appendMessage('Maaf, sistem mengalami kegagalan teknis saat memproses pesan Anda.', false);
                }
            },
            error: function() {
                removeTypingIndicator();
                appendMessage('Gagal menghubungi server. Periksa koneksi internet Anda.', false);
            }
        });
    }
    
    // Form Submission
    chatForm.on('submit', function(e) {
        e.preventDefault();
        const msg = $.trim(chatInput.val());
        sendMessage(msg);
    });
    
    // Quick Chips Clicks
    $('.quick-chip').on('click', function() {
        const text = $(this).data('text');
        sendMessage(text);
    });
});
</script>
JS;

require_once __DIR__ . '/../../includes/footer.php';
?>
