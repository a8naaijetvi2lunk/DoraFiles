    </div>

    <?php if (isAuthenticated()): ?>
    <footer class="footer">
        <div class="footer-content">
            <p>
                Développé avec <span class="footer-heart">❤</span> par <a href="https://yvescharvis.fr" target="_blank" rel="noopener">Yves Charvis</a>
                <span class="footer-separator">•</span>
                <a href="#" onclick="openPatchNotesModal(); return false;" class="footer-link">Patch Notes</a>
            </p>
        </div>
    </footer>

    <!-- Patch Notes Modal -->
    <div id="patchNotesModal" class="modal">
        <div class="modal-overlay" onclick="closePatchNotesModal()"></div>
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2>Notes de version</h2>
                <button class="modal-close" onclick="closePatchNotesModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="patchNotesContent" class="markdown-content">
                    <p class="text-center text-muted">Chargement...</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Modal handling
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Copy to clipboard
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Lien copié dans le presse-papiers!');
            });
        }

        // Patch Notes Modal handling
        function openPatchNotesModal() {
            const modal = document.getElementById('patchNotesModal');
            modal.classList.add('active');

            // Load patch notes content
            fetch('/patch-notes.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('patchNotesContent').innerHTML = markdownToHtml(data.content);
                    } else {
                        document.getElementById('patchNotesContent').innerHTML = '<p class="text-danger">Erreur lors du chargement des notes de version.</p>';
                    }
                })
                .catch(error => {
                    console.error('Error loading patch notes:', error);
                    document.getElementById('patchNotesContent').innerHTML = '<p class="text-danger">Erreur lors du chargement des notes de version.</p>';
                });
        }

        function closePatchNotesModal() {
            document.getElementById('patchNotesModal').classList.remove('active');
        }

        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // Process inline Markdown (bold, italic, links, code)
        function processInlineMarkdown(text) {
            // Code blocks (must be first to avoid processing content inside)
            text = text.replace(/`([^`]+)`/g, '<code>$1</code>');

            // Bold text
            text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
            text = text.replace(/__(.+?)__/g, '<strong>$1</strong>');

            // Italic text
            text = text.replace(/\*(.+?)\*/g, '<em>$1</em>');
            text = text.replace(/_(.+?)_/g, '<em>$1</em>');

            // Links
            text = text.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');

            // Strikethrough
            text = text.replace(/~~(.+?)~~/g, '<del>$1</del>');

            return text;
        }

        // Enhanced Markdown to HTML converter with visual elements
        function markdownToHtml(markdown) {
            let lines = markdown.split('\n');
            let html = '';
            let currentVersion = null;
            let currentSection = null;
            let inList = false;
            let inCodeBlock = false;
            let codeBlockContent = '';

            for (let i = 0; i < lines.length; i++) {
                let line = lines[i];

                // Code blocks (```)
                if (line.trim().match(/^```/)) {
                    if (!inCodeBlock) {
                        inCodeBlock = true;
                        codeBlockContent = '';
                        continue;
                    } else {
                        inCodeBlock = false;
                        html += `<pre><code>${escapeHtml(codeBlockContent)}</code></pre>`;
                        codeBlockContent = '';
                        continue;
                    }
                }

                // Inside code block
                if (inCodeBlock) {
                    codeBlockContent += line + '\n';
                    continue;
                }

                // Version header (## [Version X.X.X] - Date)
                if (line.match(/^## \[Version ([\d.]+)\]/)) {
                    if (inList) {
                        html += '</ul>';
                        inList = false;
                    }
                    if (currentVersion) {
                        html += '</div>'; // Close previous version block
                    }

                    let versionMatch = line.match(/^## \[Version ([\d.]+)\] - (.+)$/);
                    let versionNumber = versionMatch[1];
                    let versionDate = versionMatch[2];

                    html += `<div class="version-block">
                        <div class="version-header">
                            <span class="version-badge">v${versionNumber}</span>
                            <span class="version-date">${versionDate}</span>
                        </div>`;
                    currentVersion = versionNumber;
                }
                // Main title (# Changelog)
                else if (line.match(/^# /)) {
                    let title = line.replace(/^# /, '');
                    html += `<div class="changelog-title">${title}</div>`;
                }
                // Section headers (### Ajouté, ### Amélioré, etc.)
                else if (line.match(/^### /)) {
                    if (inList) {
                        html += '</ul>';
                        inList = false;
                    }
                    let section = line.replace(/^### /, '');
                    let badgeClass = '';
                    let icon = '';

                    switch(section.toLowerCase()) {
                        case 'ajouté':
                            badgeClass = 'badge-added';
                            icon = '✨';
                            break;
                        case 'amélioré':
                            badgeClass = 'badge-improved';
                            icon = '🚀';
                            break;
                        case 'sécurité':
                            badgeClass = 'badge-security';
                            icon = '🔒';
                            break;
                        case 'corrigé':
                            badgeClass = 'badge-fixed';
                            icon = '🐛';
                            break;
                        default:
                            badgeClass = 'badge-default';
                            icon = '📝';
                    }

                    html += `<div class="section-header ${badgeClass}">
                        <span class="section-icon">${icon}</span>
                        <span class="section-title">${section}</span>
                    </div>`;
                    currentSection = section;
                }
                // List items
                else if (line.match(/^[\-\*] /)) {
                    if (!inList) {
                        html += '<ul class="changelog-list">';
                        inList = true;
                    }
                    let content = line.replace(/^[\-\*] /, '');
                    content = processInlineMarkdown(content);
                    html += `<li>${content}</li>`;
                }
                // Empty lines
                else if (line.trim() === '') {
                    if (inList) {
                        html += '</ul>';
                        inList = false;
                    }
                }
                // Regular paragraphs
                else if (line.trim() !== '') {
                    if (inList) {
                        html += '</ul>';
                        inList = false;
                    }
                    let content = processInlineMarkdown(line);
                    html += `<p>${content}</p>`;
                }
            }

            // Close any remaining open tags
            if (inList) {
                html += '</ul>';
            }
            if (currentVersion) {
                html += '</div>'; // Close last version block
            }

            return html;
        }

        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePatchNotesModal();
            }
        });
    </script>
</body>
</html>
