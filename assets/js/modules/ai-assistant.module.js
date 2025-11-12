/**
 * AI Assistant Module
 * Provides AI-powered chat and diagnostic capabilities
 */

class AIAssistantModule extends BaseModule {
    
    constructor() {
        super();
        this.chatHistory = [];
        this.context = {};
    }
    
    async load() {
        await this.render();
        this.initHandlers();
        await this.loadContext();
    }
    
    async render() {
        const html = await this.apiRequest('/api/view?view=ai-assistant-admin');
        this.updateContent(html);
    }
    
    async loadContext() {
        try {
            // Load site context for better AI responses
            const response = await WPSafeMode.API.get('/api/data?type=info');
            if (response.success && response.data) {
                this.context = response.data;
            }
        } catch (error) {
            console.error('Failed to load context:', error);
        }
    }
    
    initHandlers() {
        // Chat form submission
        const chatForm = document.getElementById('ai-chat-form');
        if (chatForm) {
            chatForm.addEventListener('submit', (e) => this.handleChatSubmit(e));
        }
        
        // Quick action buttons
        const analyzeErrorBtn = document.getElementById('ai-analyze-error-log');
        if (analyzeErrorBtn) {
            analyzeErrorBtn.addEventListener('click', () => this.analyzeErrorLog());
        }
        
        const detectConflictsBtn = document.getElementById('ai-detect-conflicts');
        if (detectConflictsBtn) {
            detectConflictsBtn.addEventListener('click', () => this.detectConflicts());
        }
        
        const securityAnalysisBtn = document.getElementById('ai-security-analysis');
        if (securityAnalysisBtn) {
            securityAnalysisBtn.addEventListener('click', () => this.securityAnalysis());
        }
        
        const performanceBtn = document.getElementById('ai-performance');
        if (performanceBtn) {
            performanceBtn.addEventListener('click', () => this.performanceOptimization());
        }
        
        // Clear chat button
        const clearChatBtn = document.getElementById('ai-clear-chat');
        if (clearChatBtn) {
            clearChatBtn.addEventListener('click', () => this.clearChat());
        }
        
        // Check AI configuration
        this.checkConfiguration();
    }
    
    async checkConfiguration() {
        try {
            const response = await WPSafeMode.API.post('/ai/check_config');
            const statusEl = document.getElementById('ai-status');
            if (statusEl) {
                if (response.success && response.data.configured) {
                    statusEl.innerHTML = '<span class="badge badge-success">AI Configured</span>';
                } else {
                    statusEl.innerHTML = '<span class="badge badge-warning">AI Not Configured</span>';
                    this.showMessage('Please configure OpenAI API key in Global Settings', 'warning');
                }
            }
        } catch (error) {
            console.error('Failed to check AI configuration:', error);
        }
    }
    
    async handleChatSubmit(e) {
        e.preventDefault();
        
        const input = document.getElementById('ai-chat-input');
        const message = input.value.trim();
        
        if (!message) return;
        
        // Add user message to chat
        this.addChatMessage('user', message);
        input.value = '';
        
        // Show loading
        this.showChatLoading();
        
        try {
            const response = await WPSafeMode.API.post('/ai/chat', {
                message: message,
                history: this.chatHistory,
                context: this.context
            });
            
            if (response.success && response.data.response) {
                this.addChatMessage('assistant', response.data.response);
                
                // Update chat history
                this.chatHistory.push(
                    { role: 'user', content: message },
                    { role: 'assistant', content: response.data.response }
                );
                
                // Keep only last 10 messages in history
                if (this.chatHistory.length > 20) {
                    this.chatHistory = this.chatHistory.slice(-20);
                }
            } else {
                throw new Error(response.error || 'Failed to get AI response');
            }
        } catch (error) {
            this.showMessage('Error: ' + error.message, 'error');
            this.addChatMessage('system', 'Sorry, I encountered an error. Please try again.');
        } finally {
            this.hideChatLoading();
        }
    }
    
    addChatMessage(role, content) {
        const chatContainer = document.getElementById('ai-chat-messages');
        if (!chatContainer) return;
        
        const messageEl = document.createElement('div');
        messageEl.className = `chat-message chat-message-${role}`;
        
        const roleLabel = role === 'user' ? 'You' : role === 'assistant' ? 'AI Assistant' : 'System';
        const icon = role === 'user' ? 'fa-user' : role === 'assistant' ? 'fa-robot' : 'fa-info-circle';
        
        messageEl.innerHTML = `
            <div class="chat-message-header">
                <i class="fas ${icon}"></i> ${roleLabel}
            </div>
            <div class="chat-message-content">${this.formatMessage(content)}</div>
        `;
        
        chatContainer.appendChild(messageEl);
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }
    
    formatMessage(content) {
        // Format markdown-like content
        return content
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/`(.*?)`/g, '<code>$1</code>')
            .replace(/\n/g, '<br>');
    }
    
    showChatLoading() {
        const chatContainer = document.getElementById('ai-chat-messages');
        if (!chatContainer) return;
        
        const loadingEl = document.createElement('div');
        loadingEl.id = 'ai-chat-loading';
        loadingEl.className = 'chat-message chat-message-assistant';
        loadingEl.innerHTML = `
            <div class="chat-message-header">
                <i class="fas fa-robot"></i> AI Assistant
            </div>
            <div class="chat-message-content">
                <i class="fas fa-spinner fa-spin"></i> Thinking...
            </div>
        `;
        chatContainer.appendChild(loadingEl);
        chatContainer.scrollTop = chatContainer.scrollHeight;
    }
    
    hideChatLoading() {
        const loadingEl = document.getElementById('ai-chat-loading');
        if (loadingEl) {
            loadingEl.remove();
        }
    }
    
    clearChat() {
        const chatContainer = document.getElementById('ai-chat-messages');
        if (chatContainer) {
            chatContainer.innerHTML = '';
        }
        this.chatHistory = [];
        this.addChatMessage('system', 'Chat cleared. How can I help you with your WordPress site?');
    }
    
    async analyzeErrorLog() {
        this.showMessage('Analyzing error log...', 'info');
        
        try {
            // Get error log content
            const errorLogResponse = await WPSafeMode.API.get('/api/data?type=error_log');
            
            if (!errorLogResponse.success || !errorLogResponse.data || !errorLogResponse.data.content) {
                throw new Error('No error log content available');
            }
            
            const analysisResponse = await WPSafeMode.API.post('/ai/analyze_error_log', {
                error_log: errorLogResponse.data.content
            });
            
            if (analysisResponse.success && analysisResponse.data.analysis) {
                this.showAnalysisModal('Error Log Analysis', analysisResponse.data.analysis);
            } else {
                throw new Error(analysisResponse.error || 'Analysis failed');
            }
        } catch (error) {
            this.showMessage('Error: ' + error.message, 'error');
        }
    }
    
    async detectConflicts() {
        this.showMessage('Detecting plugin conflicts...', 'info');
        
        try {
            // Get plugins and error log
            const pluginsResponse = await WPSafeMode.API.get('/api/data?type=plugins');
            const errorLogResponse = await WPSafeMode.API.get('/api/data?type=error_log');
            
            if (!pluginsResponse.success || !pluginsResponse.data) {
                throw new Error('Failed to load plugins');
            }
            
            const plugins = [];
            if (pluginsResponse.data.active) {
                plugins.push(...pluginsResponse.data.active.map(p => p.name || p));
            }
            if (pluginsResponse.data.inactive) {
                plugins.push(...pluginsResponse.data.inactive.map(p => p.name || p));
            }
            
            const errorLog = errorLogResponse.success && errorLogResponse.data 
                ? errorLogResponse.data.content 
                : '';
            
            const analysisResponse = await WPSafeMode.API.post('/ai/detect_conflicts', {
                plugins: plugins,
                error_log: errorLog
            });
            
            if (analysisResponse.success && analysisResponse.data.analysis) {
                this.showAnalysisModal('Plugin Conflict Analysis', analysisResponse.data.analysis);
            } else {
                throw new Error(analysisResponse.error || 'Analysis failed');
            }
        } catch (error) {
            this.showMessage('Error: ' + error.message, 'error');
        }
    }
    
    async securityAnalysis() {
        this.showMessage('Running security analysis...', 'info');
        
        try {
            const response = await WPSafeMode.API.post('/ai/security_analysis');
            
            if (response.success && response.data.analysis) {
                this.showAnalysisModal('Security Analysis', response.data.analysis);
            } else {
                throw new Error(response.error || 'Analysis failed');
            }
        } catch (error) {
            this.showMessage('Error: ' + error.message, 'error');
        }
    }
    
    async performanceOptimization() {
        this.showMessage('Analyzing performance...', 'info');
        
        try {
            const response = await WPSafeMode.API.post('/ai/performance_optimization');
            
            if (response.success && response.data.analysis) {
                this.showAnalysisModal('Performance Optimization', response.data.analysis);
            } else {
                throw new Error(response.error || 'Analysis failed');
            }
        } catch (error) {
            this.showMessage('Error: ' + error.message, 'error');
        }
    }
    
    showAnalysisModal(title, content) {
        // Create or update modal
        let modal = document.getElementById('ai-analysis-modal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'ai-analysis-modal';
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h4 class="modal-title"></h4>
                            <button type="button" class="close" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body"></div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        modal.querySelector('.modal-title').textContent = title;
        modal.querySelector('.modal-body').innerHTML = this.formatMessage(content);
        
        // Show modal using Bootstrap/jQuery
        if (window.jQuery) {
            $(modal).modal('show');
        } else {
            modal.style.display = 'block';
        }
    }
    
    cleanup() {
        // Cleanup if needed
    }
}

// Register module
if (typeof WPSafeMode !== 'undefined' && WPSafeMode.Router) {
    WPSafeMode.Router.registerModule('ai-assistant', AIAssistantModule);
}

