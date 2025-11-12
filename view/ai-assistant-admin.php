<?php
/**
 * AI Assistant View - AdminLTE Design
 * AI-powered troubleshooting and diagnostics
 */
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">AI Assistant</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="#" data-view="info">Home</a></li>
                    <li class="breadcrumb-item active">AI Assistant</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <!-- AI Status -->
        <div class="row">
            <div class="col-12">
                <div class="card card-info">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-robot mr-2"></i>AI Status
                        </h3>
                        <div class="card-tools">
                            <span id="ai-status" class="badge badge-secondary">Checking...</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <p>AI Assistant is powered by OpenAI GPT-4. Configure your API key in Global Settings to enable AI features.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-bolt mr-2"></i>Quick AI Analysis
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 col-sm-6">
                                <button id="ai-analyze-error-log" class="btn btn-block btn-outline-primary">
                                    <i class="fas fa-file-alt mr-2"></i>Analyze Error Log
                                </button>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <button id="ai-detect-conflicts" class="btn btn-block btn-outline-warning">
                                    <i class="fas fa-exclamation-triangle mr-2"></i>Detect Conflicts
                                </button>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <button id="ai-security-analysis" class="btn btn-block btn-outline-danger">
                                    <i class="fas fa-shield-alt mr-2"></i>Security Analysis
                                </button>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <button id="ai-performance" class="btn btn-block btn-outline-success">
                                    <i class="fas fa-tachometer-alt mr-2"></i>Performance Check
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Chat Assistant -->
        <div class="row">
            <div class="col-12">
                <div class="card card-primary card-outline direct-chat direct-chat-primary">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-comments mr-2"></i>Chat with AI Assistant
                        </h3>
                        <div class="card-tools">
                            <button id="ai-clear-chat" class="btn btn-tool btn-sm" title="Clear Chat">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="ai-chat-messages" class="direct-chat-messages" style="height: 400px; overflow-y: auto;">
                            <div class="chat-message chat-message-system">
                                <div class="chat-message-header">
                                    <i class="fas fa-info-circle"></i> System
                                </div>
                                <div class="chat-message-content">
                                    Hello! I'm your AI WordPress troubleshooting assistant. How can I help you today?
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <form id="ai-chat-form">
                            <div class="input-group">
                                <input type="text" 
                                       id="ai-chat-input" 
                                       name="message" 
                                       placeholder="Type your question..." 
                                       class="form-control" 
                                       required>
                                <span class="input-group-append">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i> Send
                                    </button>
                                </span>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Features Info -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-lightbulb mr-2"></i>What Can AI Do?
                        </h3>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success mr-2"></i>Analyze error logs and suggest fixes</li>
                            <li><i class="fas fa-check text-success mr-2"></i>Detect plugin conflicts</li>
                            <li><i class="fas fa-check text-success mr-2"></i>Security vulnerability analysis</li>
                            <li><i class="fas fa-check text-success mr-2"></i>Performance optimization tips</li>
                            <li><i class="fas fa-check text-success mr-2"></i>Answer WordPress questions</li>
                            <li><i class="fas fa-check text-success mr-2"></i>Provide code suggestions</li>
                            <li><i class="fas fa-check text-success mr-2"></i>Explain errors in simple terms</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-cog mr-2"></i>Setup Instructions
                        </h3>
                    </div>
                    <div class="card-body">
                        <ol>
                            <li>Get an OpenAI API key from <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com</a></li>
                            <li>Go to <strong>Global Settings</strong> in this application</li>
                            <li>Enter your OpenAI API key</li>
                            <li>Save settings and start using AI features!</li>
                        </ol>
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle mr-2"></i>
                            <strong>Note:</strong> API usage may incur costs. Check OpenAI pricing for details.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<style>
.chat-message {
    margin-bottom: 15px;
    padding: 10px;
    border-radius: 5px;
}

.chat-message-user {
    background-color: #e3f2fd;
    margin-left: 20%;
}

.chat-message-assistant {
    background-color: #f5f5f5;
    margin-right: 20%;
}

.chat-message-system {
    background-color: #fff3cd;
    text-align: center;
    font-style: italic;
}

.chat-message-header {
    font-weight: bold;
    margin-bottom: 5px;
    font-size: 0.9em;
    color: #666;
}

.chat-message-content {
    line-height: 1.6;
}

.chat-message-content code {
    background-color: #f4f4f4;
    padding: 2px 6px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    font-size: 0.9em;
}

.direct-chat-messages {
    padding: 15px;
    background-color: #f9f9f9;
}
</style>

