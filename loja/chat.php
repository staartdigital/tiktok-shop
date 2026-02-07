<?php
// Load Config
$configFile = __DIR__ . '/database/config.json';
$config = json_decode(file_get_contents($configFile), true);
$store = $config['store'];
?>
<!DOCTYPE html>
<html lang="<?php echo $store['locale']; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Chat - <?php echo $store['name']; ?></title>
    <base href="/">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="assets/css/globals.css">
    <link rel="stylesheet" href="assets/css/chat.css">
    <?php echo $store['custom_head_code'] ?? ''; ?>
</head>

<body>
    <!-- Header -->
    <header class="chatHeader">
        <button class="iconButton backButton" onclick="history.back()">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M15 18l-6-6 6-6" />
            </svg>
        </button>
        <img src="<?php echo htmlspecialchars($store['logo']); ?>" alt="Store Logo" class="storeAvatar">
        <div class="headerInfo">
            <div class="storeName"><?php echo htmlspecialchars($store['name']); ?></div>
            <div class="storeStatus">Normalmente responde em até 24 horas</div>
        </div>
        <button class="iconButton" onclick="window.location.href='/'">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
        </button>
        <button class="iconButton">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="1"></circle>
                <circle cx="19" cy="12" r="1"></circle>
                <circle cx="5" cy="12" r="1"></circle>
            </svg>
        </button>
    </header>

    <!-- Chat Area -->
    <div class="chatContainer">
        <div class="timestamp" id="chatTime">--:--</div>

        <!-- Typing Indicator -->
        <div class="botRow" id="typingIndicator">
            <div class="botAvatar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="4" y="8" width="16" height="12" rx="2" />
                    <line x1="8" y1="4" x2="8" y2="8" />
                    <line x1="16" y1="4" x2="16" y2="8" />
                    <circle cx="9" cy="13" r="1" fill="currentColor" />
                    <circle cx="15" cy="13" r="1" fill="currentColor" />
                    <path d="M9 17h6" />
                </svg>
            </div>
            <div class="typingBubble">
                <div class="typingDot"></div>
                <div class="typingDot"></div>
                <div class="typingDot"></div>
            </div>
        </div>

        <!-- Bot Message -->
        <div class="botRow hidden" id="welcomeMessage">
            <div class="botAvatar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <rect x="4" y="8" width="16" height="12" rx="2" />
                    <line x1="8" y1="4" x2="8" y2="8" />
                    <line x1="16" y1="4" x2="16" y2="8" />
                    <circle cx="9" cy="13" r="1" fill="currentColor" />
                    <circle cx="15" cy="13" r="1" fill="currentColor" />
                    <path d="M9 17h6" />
                </svg>
            </div>
            <div class="messageGroup">
                <div class="messageBubble">
                    Olá, o grupo <?php echo htmlspecialchars($store['name']); ?> agradece por entrar em contato. Como
                    posso ajudar hoje?
                </div>

                <!-- FAQ Card -->
                <div class="faqCard">
                    <div class="faqHeader">Como posso ajudar você hoje?</div>
                    <div class="faqList">
                        <?php
                        $faqList = $config['faq'] ?? [];
                        foreach ($faqList as $f):
                            ?>
                            <button class="faqItem" onclick="handleQuestion('<?php echo htmlspecialchars($f['id']); ?>')">
                                <span><?php echo htmlspecialchars($f['question']); ?></span>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="2">
                                    <path d="M9 18l6-6-6-6" />
                                </svg>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- <div class="sentBy">Enviado por chatbot</div> -->
                <!-- Na referencia o texto 'Enviado por chatbot' parece estar fora do balao/card, abaixo dele -->
                <div class="sentBy">Enviado por chatbot</div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quickActions">
        <button class="quickBtn" onclick="window.location.href='/'">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
            Visitar loja
        </button>
    </div> <!-- Fechamento da div quickActions ajustada abaixo -->

    <!-- Input Area -->
    <div class="chatInputArea">
        <input type="text" class="chatInput" id="chatInput" placeholder="Enviar mensagem...">
        <!-- Additional Actions Button (Plus) -->
        <button class="iconButton">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                <line x1="9" y1="9" x2="9.01" y2="9"></line>
                <line x1="15" y1="9" x2="15.01" y2="9"></line>
            </svg>
        </button>
        <!-- Send Message Button -->
        <button class="iconButton" id="sendBtn" style="color: #fe2c55;">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"
                style="transform: rotate(-45deg); margin-left:2px; margin-bottom:4px;">
                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"></path>
            </svg>
        </button>
    </div>

    <script>
        const answers = {
            <?php foreach ($faqList as $f): ?>
                                                    '<?php echo $f['id']; ?>': '<?php echo addslashes($f['answer']); ?>',
            <?php endforeach; ?>
        };

        const questionsText = {
            <?php foreach ($faqList as $f): ?>
                                                    '<?php echo $f['id']; ?>': '<?php echo addslashes($f['question']); ?>',
            <?php endforeach; ?>
        };

        function handleQuestion(key) {
            const chatContainer = document.querySelector('.chatContainer');

            // User Message
            const userRow = document.createElement('div');
            userRow.className = 'userRow';
            userRow.innerHTML = `<div class="userBubble">${questionsText[key]}</div>`;
            chatContainer.appendChild(userRow);
            chatContainer.scrollTop = chatContainer.scrollHeight;

            // Show Typing clone
            const typing = document.getElementById('typingIndicator').cloneNode(true);
            typing.id = '';
            typing.style.display = 'flex';
            chatContainer.appendChild(typing);
            chatContainer.scrollTop = chatContainer.scrollHeight;

            // Simulate delay and show Bot Answer
            setTimeout(() => {
                typing.remove();

                const botRow = document.createElement('div');
                botRow.className = 'botRow';
                botRow.innerHTML = `
                    <div class="botAvatar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width: 18px; height: 18px;">
                            <rect x="4" y="8" width="16" height="12" rx="2"></rect>
                            <line x1="8" y1="4" x2="8" y2="8"></line>
                            <line x1="16" y1="4" x2="16" y2="8"></line>
                            <circle cx="9" cy="13" r="1" fill="currentColor"></circle>
                            <circle cx="15" cy="13" r="1" fill="currentColor"></circle>
                            <path d="M9 17h6"></path>
                        </svg>
                    </div>
                    <div class="messageGroup">
                        <div class="messageBubble">${answers[key]}</div>
                        <div class="sentBy">Enviado por chatbot</div>
                    </div>
                `;
                chatContainer.appendChild(botRow);
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }, 1000);
        }

        document.addEventListener('DOMContentLoaded', () => {
            const typing = document.getElementById('typingIndicator');
            const msg = document.getElementById('welcomeMessage');

            // Simulate initial bot typing/loading
            setTimeout(() => {
                if (typing) typing.style.display = 'none';
                if (msg) {
                    msg.classList.remove('hidden');
                    msg.style.display = 'flex';
                }
            }, 1000);
        });

        // Dynamic Time
        function updateTime() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const timeElement = document.getElementById('chatTime');
            if (timeElement) {
                timeElement.textContent = `${hours}:${minutes}`;
            }
        }
        setInterval(updateTime, 1000); // Update every second to be accurate
        updateTime(); // Initial call

        // Manual Message Logic
        const chatInput = document.getElementById('chatInput');
        const sendBtn = document.getElementById('sendBtn');
        const autoReplyText = <?php echo json_encode($store['chat_auto_reply'] ?? 'Em breve um atendente entrará em contato.'); ?>;

        function sendMessage() {
            const text = chatInput.value.trim();
            if (!text) return;

            const chatContainer = document.querySelector('.chatContainer');

            // User Message
            const userRow = document.createElement('div');
            userRow.className = 'userRow';
            userRow.innerHTML = `<div class="userBubble">${text}</div>`;
            chatContainer.appendChild(userRow);
            chatContainer.scrollTop = chatContainer.scrollHeight;

            chatInput.value = '';

            // Show Typing
            const typing = document.getElementById('typingIndicator').cloneNode(true);
            typing.id = 'tempTyping';
            typing.style.display = 'flex';
            chatContainer.appendChild(typing);
            chatContainer.scrollTop = chatContainer.scrollHeight;

            // Auto Reply after delay
            setTimeout(() => {
                const tempTyping = document.getElementById('tempTyping');
                if (tempTyping) tempTyping.remove();

                const botRow = document.createElement('div');
                botRow.className = 'botRow';
                botRow.innerHTML = `
                    <div class="botAvatar">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width: 18px; height: 18px;">
                            <rect x="4" y="8" width="16" height="12" rx="2"></rect>
                            <line x1="8" y1="4" x2="8" y2="8"></line>
                            <line x1="16" y1="4" x2="16" y2="8"></line>
                            <circle cx="9" cy="13" r="1" fill="currentColor"></circle>
                            <circle cx="15" cy="13" r="1" fill="currentColor"></circle>
                            <path d="M9 17h6"></path>
                        </svg>
                    </div>
                    <div class="messageGroup">
                        <div class="messageBubble">${autoReplyText}</div>
                        <div class="sentBy">Enviado por chatbot</div>
                    </div>
                `;
                chatContainer.appendChild(botRow);
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }, 1000);
        }

        sendBtn.addEventListener('click', sendMessage);
        chatInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') sendMessage();
        });
    </script>
</body>

</html>