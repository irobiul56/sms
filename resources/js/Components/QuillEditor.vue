<script setup>
import { ref, onMounted, onBeforeUnmount, watch, defineEmits, defineProps, nextTick } from 'vue';

const props = defineProps({
    modelValue: String,
    config: Object,
});

const emit = defineEmits(['update:modelValue']);
const editorId = `quill-${Math.random().toString(36).substr(2, 9)}`;
const editorInstance = ref(null);
const isQuillReady = ref(false);

// Table dialog state
const showTableDialog = ref(false);
const tableRows = ref(3);
const tableCols = ref(3);

const defaultConfig = {
    theme: 'snow',
    modules: {
        toolbar: [
            [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
            [{ 'font': [] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ 'color': [] }, { 'background': [] }],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            [{ 'align': [] }],
            ['blockquote', 'code-block'],
            ['link', 'image'],
            ['clean'],
        ]
    },
    placeholder: 'Write something...',
    readOnly: false
};

const initializeEditor = () => {
    try {
        if (typeof Quill === 'undefined') {
            console.error('Quill is not loaded');
            return;
        }

        const config = { 
            ...defaultConfig, 
            ...props.config,
            modules: {
                ...defaultConfig.modules,
                ...props.config?.modules,
            }
        };

        const editorElement = document.getElementById(editorId);
        if (!editorElement) {
            console.error('Editor element not found');
            return;
        }

        // Create editor instance
        editorInstance.value = new Quill(editorElement, config);
        isQuillReady.value = true;

        // Set initial content
        if (props.modelValue) {
            editorInstance.value.root.innerHTML = props.modelValue;
        }

        // Handle text change
        editorInstance.value.on('text-change', function(delta, oldDelta, source) {
            if (source === 'user') {
                const html = editorInstance.value.root.innerHTML;
                emit('update:modelValue', html === '<p><br></p>' ? '' : html);
            }
        });

        // Add table button after editor is ready
        setTimeout(() => {
            addTableButtonToToolbar();
        }, 100);

        console.log('Editor initialized successfully');

    } catch (error) {
        console.error('Editor initialization error:', error);
    }
};

// Add table button to toolbar
const addTableButtonToToolbar = () => {
    if (!editorInstance.value) return;

    // Find the toolbar container
    const toolbarContainer = document.querySelector(`#${editorId} .ql-toolbar`);
    if (!toolbarContainer) {
        console.warn('Toolbar not found, retrying...');
        setTimeout(addTableButtonToToolbar, 500);
        return;
    }

    // Check if table button already exists
    if (toolbarContainer.querySelector('.ql-table-custom')) {
        return;
    }

    // Find the last ql-formats group or create a new one
    let formatGroup = toolbarContainer.querySelector('.ql-formats:last-child');
    if (!formatGroup) {
        formatGroup = document.createElement('span');
        formatGroup.className = 'ql-formats';
        toolbarContainer.appendChild(formatGroup);
    }

    // Create table button
    const tableBtn = document.createElement('button');
    tableBtn.type = 'button';
    tableBtn.className = 'ql-table-custom';
    tableBtn.innerHTML = '📊';
    tableBtn.title = 'Insert Table';
    tableBtn.style.cssText = `
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 4px 8px;
        border: none;
        background: none;
        cursor: pointer;
        font-size: 16px;
        border-radius: 3px;
        transition: all 0.2s;
        min-width: 28px;
        height: 28px;
    `;
    
    // Hover effect
    tableBtn.addEventListener('mouseenter', () => {
        tableBtn.style.backgroundColor = '#e0e0e0';
    });
    tableBtn.addEventListener('mouseleave', () => {
        tableBtn.style.backgroundColor = 'transparent';
    });

    // Click handler
    tableBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        toggleTableDialog();
    });

    // Add the button to the format group
    formatGroup.appendChild(tableBtn);
    
    console.log('Table button added to toolbar successfully');
};

// Toggle table dialog
const toggleTableDialog = () => {
    showTableDialog.value = !showTableDialog.value;
    if (showTableDialog.value) {
        nextTick(() => {
            positionTableDialog();
            // Focus on first input
            const input = document.querySelector('#table-rows');
            if (input) setTimeout(() => input.focus(), 100);
        });
    }
};

// Position the table dialog
const positionTableDialog = () => {
    const dialog = document.querySelector('.ql-table-dialog');
    if (!dialog) return;

    const btn = document.querySelector('.ql-table-custom');
    if (!btn) return;

    const rect = btn.getBoundingClientRect();
    const dialogWidth = 300;
    const left = Math.min(
        rect.left - dialogWidth / 2 + rect.width / 2,
        window.innerWidth - dialogWidth - 10
    );
    
    dialog.style.position = 'fixed';
    dialog.style.top = (rect.bottom + 5) + 'px';
    dialog.style.left = Math.max(10, left) + 'px';
    dialog.style.zIndex = '10000';
    dialog.style.display = 'block';
};

// Insert table into editor
const insertTable = () => {
    if (!editorInstance.value) return;
    
    try {
        const rows = parseInt(tableRows.value) || 3;
        const cols = parseInt(tableCols.value) || 3;

        if (rows < 1 || cols < 1) {
            alert('Please enter valid row and column numbers (minimum 1)');
            return;
        }

        if (rows > 20 || cols > 20) {
            alert('Maximum 20 rows and columns allowed');
            return;
        }

        // Create table HTML
        let tableHtml = `<table style="border-collapse: collapse; width: 100%; margin: 10px 0; border: 1px solid #ccc;"><tbody>`;
        
        for (let i = 0; i < rows; i++) {
            tableHtml += '<tr>';
            for (let j = 0; j < cols; j++) {
                tableHtml += '<td style="border: 1px solid #ccc; padding: 8px; min-width: 50px;"><br></td>';
            }
            tableHtml += '</tr>';
        }
        tableHtml += '</tbody></table>';

        // Get current cursor position
        const range = editorInstance.value.getSelection();
        
        // Insert at cursor position
        if (range) {
            const delta = editorInstance.value.clipboard.convert(tableHtml);
            editorInstance.value.updateContents(delta);
            const newIndex = range.index + tableHtml.length;
            editorInstance.value.setSelection(newIndex, 0);
        } else {
            // Insert at end if no selection
            const length = editorInstance.value.getLength();
            const delta = editorInstance.value.clipboard.convert(tableHtml);
            editorInstance.value.updateContents(delta);
            editorInstance.value.setSelection(length + tableHtml.length, 0);
        }

        // Emit change
        const html = editorInstance.value.root.innerHTML;
        emit('update:modelValue', html === '<p><br></p>' ? '' : html);
        
        // Close dialog and reset
        showTableDialog.value = false;
        tableRows.value = 3;
        tableCols.value = 3;
        
    } catch (error) {
        console.error('Error inserting table:', error);
        alert('Error inserting table. Please try again.');
    }
};

// Cancel table insertion
const cancelTable = () => {
    showTableDialog.value = false;
    tableRows.value = 3;
    tableCols.value = 3;
};

// Watch for modelValue changes
watch(() => props.modelValue, (newValue) => {
    if (editorInstance.value && isQuillReady.value) {
        const currentContent = editorInstance.value.root.innerHTML;
        if (newValue !== currentContent) {
            const selection = editorInstance.value.getSelection();
            editorInstance.value.root.innerHTML = newValue || '';
            if (selection) {
                editorInstance.value.setSelection(selection);
            }
        }
    }
});

// Close dialog on outside click
const handleOutsideClick = (e) => {
    if (showTableDialog.value) {
        const dialog = document.querySelector('.ql-table-dialog');
        const btn = document.querySelector('.ql-table-custom');
        if (dialog && !dialog.contains(e.target) && btn && !btn.contains(e.target)) {
            showTableDialog.value = false;
            tableRows.value = 3;
            tableCols.value = 3;
        }
    }
};

// Handle keyboard events
const handleKeydown = (e) => {
    if (showTableDialog.value) {
        if (e.key === 'Escape') {
            cancelTable();
        }
        if (e.key === 'Enter') {
            const target = e.target;
            if (target && (target.id === 'table-rows' || target.id === 'table-cols')) {
                e.preventDefault();
                insertTable();
            }
        }
    }
};

// Lifecycle hooks
onMounted(() => {
    if (typeof Quill !== 'undefined') {
        initializeEditor();
    } else {
        // Load Quill CSS
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://cdn.quilljs.com/1.3.7/quill.snow.css';
        document.head.appendChild(link);
        
        // Load Quill JS
        const script = document.createElement('script');
        script.src = 'https://cdn.quilljs.com/1.3.7/quill.js';
        script.onload = () => {
            nextTick(() => {
                initializeEditor();
            });
        };
        script.onerror = () => {
            console.error('Failed to load Quill');
        };
        document.head.appendChild(script);
    }

    // Add event listeners
    document.addEventListener('click', handleOutsideClick);
    document.addEventListener('keydown', handleKeydown);
});

onBeforeUnmount(() => {
    if (editorInstance.value) {
        const container = editorInstance.value.container;
        if (container && container.parentNode) {
            container.parentNode.removeChild(container);
        }
        editorInstance.value = null;
        isQuillReady.value = false;
    }

    // Remove dialog if exists
    const dialog = document.querySelector('.ql-table-dialog');
    if (dialog) {
        dialog.remove();
    }

    document.removeEventListener('click', handleOutsideClick);
    document.removeEventListener('keydown', handleKeydown);
});

// Expose methods
defineExpose({
    insertTable,
    getContent: () => editorInstance.value?.root.innerHTML || '',
    setContent: (content) => {
        if (editorInstance.value && isQuillReady.value) {
            editorInstance.value.root.innerHTML = content;
        }
    },
    getEditor: () => editorInstance.value
});
</script>

<template>
    <div class="quill-editor-wrapper">
        <div :id="editorId" ref="editorContainer"></div>

        <!-- Table Insert Dialog -->
        <div v-if="showTableDialog" class="ql-table-dialog" style="display: block;">
            <div class="ql-table-dialog-content">
                <div class="ql-table-dialog-header">
                    <h4 class="ql-table-dialog-title">Insert Table</h4>
                    <button class="ql-table-dialog-close" @click="cancelTable">×</button>
                </div>
                
                <div class="ql-table-dialog-fields">
                    <div class="ql-table-dialog-field">
                        <label for="table-rows" class="ql-table-dialog-label">Rows</label>
                        <input 
                            id="table-rows"
                            type="number" 
                            v-model.number="tableRows" 
                            min="1" 
                            max="20"
                            class="ql-table-dialog-input"
                            @keydown.enter="insertTable"
                        />
                    </div>
                    <div class="ql-table-dialog-field">
                        <label for="table-cols" class="ql-table-dialog-label">Columns</label>
                        <input 
                            id="table-cols"
                            type="number" 
                            v-model.number="tableCols" 
                            min="1" 
                            max="20"
                            class="ql-table-dialog-input"
                            @keydown.enter="insertTable"
                        />
                    </div>
                </div>

                <div class="ql-table-dialog-actions">
                    <button class="ql-table-dialog-btn ql-table-dialog-btn-cancel" @click="cancelTable">
                        Cancel
                    </button>
                    <button class="ql-table-dialog-btn ql-table-dialog-btn-insert" @click="insertTable">
                        Insert Table
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.quill-editor-wrapper {
    position: relative;
    width: 100%;
}

/* Quill container styling */
:deep(.ql-container) {
    font-family: 'Helvetica Neue', Arial, sans-serif;
    font-size: 13px;
    min-height: 200px;
    border-bottom-left-radius: 4px;
    border-bottom-right-radius: 4px;
}

:deep(.ql-toolbar) {
    border-top-left-radius: 4px;
    border-top-right-radius: 4px;
    background: #fafafa;
    display: flex;
    flex-wrap: wrap;
    gap: 2px;
    padding: 8px;
    border-bottom: 1px solid #ccc;
}

:deep(.ql-toolbar .ql-formats) {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    margin: 0 2px;
}

:deep(.ql-editor) {
    line-height: 1.6;
    min-height: 200px;
}

:deep(.ql-editor p) {
    margin-bottom: 1em;
}

:deep(.ql-editor.ql-blank::before) {
    font-style: italic;
    color: #999;
}

/* Table styles */
:deep(.ql-editor table) {
    border-collapse: collapse;
    width: 100%;
    margin: 10px 0;
}

:deep(.ql-editor td) {
    border: 1px solid #ccc;
    padding: 8px;
    min-width: 50px;
    vertical-align: top;
}

:deep(.ql-editor td:focus) {
    outline: 2px solid #4CAF50;
    outline-offset: -1px;
}

/* Custom table button */
:deep(.ql-table-custom) {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    padding: 4px 8px;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 16px;
    border-radius: 3px;
    transition: all 0.2s;
    min-width: 28px;
    height: 28px;
}

:deep(.ql-table-custom:hover) {
    background-color: #e0e0e0;
}

:deep(.ql-table-custom:active) {
    transform: scale(0.95);
}

/* Table Dialog */
.ql-table-dialog {
    position: fixed;
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    min-width: 280px;
    max-width: 350px;
    z-index: 10000;
    animation: slideDown 0.2s ease;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.ql-table-dialog-content {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.ql-table-dialog-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 12px;
    border-bottom: 1px solid #eee;
}

.ql-table-dialog-title {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.ql-table-dialog-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #999;
    padding: 0 4px;
    transition: color 0.2s;
    line-height: 1;
}

.ql-table-dialog-close:hover {
    color: #333;
}

.ql-table-dialog-fields {
    display: flex;
    gap: 16px;
}

.ql-table-dialog-field {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.ql-table-dialog-label {
    font-size: 13px;
    font-weight: 500;
    color: #555;
}

.ql-table-dialog-input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    width: 100%;
    transition: border-color 0.2s;
}

.ql-table-dialog-input:focus {
    outline: none;
    border-color: #4CAF50;
    box-shadow: 0 0 0 2px rgba(76, 175, 80, 0.1);
}

.ql-table-dialog-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    padding-top: 12px;
    border-top: 1px solid #eee;
}

.ql-table-dialog-btn {
    padding: 8px 16px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.ql-table-dialog-btn-cancel {
    background: #f5f5f5;
    color: #666;
}

.ql-table-dialog-btn-cancel:hover {
    background: #e0e0e0;
}

.ql-table-dialog-btn-insert {
    background: #4CAF50;
    color: white;
}

.ql-table-dialog-btn-insert:hover {
    background: #45a049;
}

.ql-table-dialog-btn-insert:active {
    transform: scale(0.98);
}

/* Responsive */
@media (max-width: 600px) {
    .ql-table-dialog {
        min-width: 200px;
        max-width: 280px;
        padding: 16px;
        left: 50% !important;
        transform: translateX(-50%);
    }
    
    .ql-table-dialog-fields {
        flex-direction: column;
        gap: 8px;
    }
}
</style>