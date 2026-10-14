/*! Minimalist Web Notepad | https://github.com/pereorga/minimalist-web-notepad */

var textarea = document.getElementById('content');
var printable = document.getElementById('printable');
var previewBox = document.getElementById('preview');
var statusBox = document.getElementById('status');
var countBox = document.getElementById('count');
var fileInput = document.getElementById('file-input');
var content = textarea.value;
var mode = 'edit';
var statusTimer = null;
var renderTimer = null;

// ---------------------------------------------------------------- rendering

function renderPreview() {
    previewBox.innerHTML = DOMPurify.sanitize(marked.parse(textarea.value));
    // Syntax highlighting for code blocks.
    if (window.hljs) {
        var blocks = previewBox.querySelectorAll('pre code');
        for (var i = 0; i < blocks.length; i++) {
            hljs.highlightElement(blocks[i]);
        }
    }
}

function onInput() {
    if (countBox) {
        countBox.textContent = textarea.value.length + ' 字';
    }
    if (mode !== 'edit') {
        if (renderTimer) {
            clearTimeout(renderTimer);
        }
        renderTimer = setTimeout(renderPreview, 250);
    }
}

// ---------------------------------------------------------------- view mode

function setMode(m) {
    mode = m;
    document.body.setAttribute('data-mode', m);
    try { localStorage.setItem('mwn-mode', m); } catch (e) { /* ignore */ }
    var buttons = document.querySelectorAll('.mode-btn');
    for (var i = 0; i < buttons.length; i++) {
        if (buttons[i].getAttribute('data-mode') === m) {
            buttons[i].classList.add('active');
        } else {
            buttons[i].classList.remove('active');
        }
    }
    if (m !== 'edit') {
        renderPreview();
    }
    if (m !== 'preview') {
        textarea.focus();
    }
}

// ---------------------------------------------------------------- status

function showStatus(text, keep) {
    statusBox.textContent = text;
    statusBox.classList.add('visible');
    if (statusTimer) {
        clearTimeout(statusTimer);
    }
    if (!keep) {
        statusTimer = setTimeout(function () {
            statusBox.classList.remove('visible');
        }, 2000);
    }
}

// ---------------------------------------------------------------- editing helpers

function replaceSelection(text) {
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    textarea.setRangeText(text, start, end, 'end');
    textarea.focus();
    onInput();
}

function wrapSelection(before, after, placeholder) {
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    var selected = textarea.value.substring(start, end) || placeholder || '';
    textarea.setRangeText(before + selected + after, start, end, 'end');
    // Keep the inner text selected for further formatting.
    textarea.setSelectionRange(start + before.length, start + before.length + selected.length);
    textarea.focus();
    onInput();
}

function prefixLines(prefix) {
    var value = textarea.value;
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    var lineStart = value.lastIndexOf('\n', start - 1) + 1;
    var lineEnd = value.indexOf('\n', end);
    if (lineEnd === -1) {
        lineEnd = value.length;
    }
    var lines = value.substring(lineStart, lineEnd).split('\n');
    // Toggle: remove the prefix if every line already has it.
    var allPrefixed = true;
    for (var i = 0; i < lines.length; i++) {
        if (lines[i].indexOf(prefix) !== 0) {
            allPrefixed = false;
            break;
        }
    }
    var out = [];
    for (var j = 0; j < lines.length; j++) {
        out.push(allPrefixed ? lines[j].slice(prefix.length) : prefix + lines[j]);
    }
    textarea.setRangeText(out.join('\n'), lineStart, lineEnd, 'end');
    textarea.focus();
    onInput();
}

function insertBlock(text) {
    var start = textarea.selectionStart;
    var before = textarea.value.substring(0, start);
    var pad = (before && before.charAt(before.length - 1) !== '\n') ? '\n' : '';
    replaceSelection(pad + text);
}

// ---------------------------------------------------------------- image upload

function uploadImage(file) {
    if (!file.type || file.type.indexOf('image/') !== 0) {
        showStatus('仅支持图片文件');
        return;
    }
    if (file.size > 10 * 1024 * 1024) {
        showStatus('图片不能超过 10 MB');
        return;
    }

    var fd = new FormData();
    fd.append('image', file);
    showStatus('图片上传中…', true);

    fetch(window.location.href, { method: 'POST', body: fd })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data.url) {
                insertBlock('\n![](' + data.url + ')\n');
                showStatus('图片已上传');
            }
            else {
                showStatus('上传失败');
            }
        })
        .catch(function () {
            showStatus('上传失败');
        });
}

// ---------------------------------------------------------------- toolbar actions

var actions = {
    bold: function () { wrapSelection('**', '**', '加粗文字'); },
    italic: function () { wrapSelection('*', '*', '斜体文字'); },
    strike: function () { wrapSelection('~~', '~~', '删除线'); },
    heading: function () { prefixLines('## '); },
    ul: function () { prefixLines('- '); },
    ol: function () { prefixLines('1. '); },
    quote: function () { prefixLines('> '); },
    code: function () { wrapSelection('`', '`', '代码'); },
    codeblock: function () {
        var sel = textarea.value.substring(textarea.selectionStart, textarea.selectionEnd) || '代码';
        insertBlock('\n```\n' + sel + '\n```\n');
    },
    table: function () {
        insertBlock('\n| 列1 | 列2 | 列3 |\n|------|------|------|\n| 内容 | 内容 | 内容 |\n');
    },
    link: function () {
        var url = window.prompt('链接地址：', 'https://');
        if (url) {
            wrapSelection('[', '](' + url + ')', url);
        }
    },
    image: function () { fileInput.click(); }
};

// ---------------------------------------------------------------- events

var toolButtons = document.querySelectorAll('.tool-btn[data-action]');
for (var i = 0; i < toolButtons.length; i++) {
    toolButtons[i].addEventListener('click', function (e) {
        var action = e.currentTarget.getAttribute('data-action');
        if (actions[action]) {
            actions[action]();
        }
    });
}

var modeButtons = document.querySelectorAll('.mode-btn');
for (var j = 0; j < modeButtons.length; j++) {
    modeButtons[j].addEventListener('click', function (e) {
        setMode(e.currentTarget.getAttribute('data-mode'));
    });
}

fileInput.addEventListener('change', function () {
    if (fileInput.files && fileInput.files[0]) {
        uploadImage(fileInput.files[0]);
        fileInput.value = '';
    }
});

// Paste images directly into the editor.
textarea.addEventListener('paste', function (e) {
    var items = e.clipboardData && e.clipboardData.items;
    if (!items) {
        return;
    }
    for (var i = 0; i < items.length; i++) {
        if (items[i].kind === 'file' && items[i].type.indexOf('image/') === 0) {
            var file = items[i].getAsFile();
            if (file) {
                e.preventDefault();
                uploadImage(file);
            }
        }
    }
});

// Drag & drop images into the editor.
textarea.addEventListener('dragover', function (e) {
    e.preventDefault();
});

textarea.addEventListener('drop', function (e) {
    e.preventDefault();
    var files = e.dataTransfer && e.dataTransfer.files;
    if (!files) {
        return;
    }
    for (var i = 0; i < files.length; i++) {
        uploadImage(files[i]);
    }
});

// Scroll sync while in split mode.
textarea.addEventListener('scroll', function () {
    if (mode !== 'split') {
        return;
    }
    var max = textarea.scrollHeight - textarea.clientHeight;
    if (max > 0) {
        previewBox.scrollTop = (textarea.scrollTop / max) *
            (previewBox.scrollHeight - previewBox.clientHeight);
    }
});

// ---------------------------------------------------------------- auto-save

function uploadContent() {

    // If textarea value changes.
    if (content !== textarea.value) {
        var temp = textarea.value;
        var request = new XMLHttpRequest();

        request.open('POST', window.location.href, true);
        request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
        request.onload = function() {
            if (request.readyState === 4) {

                // Request has ended, check again after 1 second.
                content = temp;
                if (mode !== 'edit') {
                    renderPreview();
                }
                setTimeout(uploadContent, 1000);
            }
        }
        request.onerror = function() {

            // Try again after 1 second.
            setTimeout(uploadContent, 1000);
        }
        request.send('text=' + encodeURIComponent(temp));

        // Make the content available to print.
        printable.removeChild(printable.firstChild);
        printable.appendChild(document.createTextNode(temp));
    }
    else {

        // Content has not changed, check again after 1 second.
        setTimeout(uploadContent, 1000);
    }
}

// ---------------------------------------------------------------- init

// Make the content available to print.
printable.appendChild(document.createTextNode(content));

// Restore last view mode, default to split on wide screens.
var savedMode = null;
try { savedMode = localStorage.getItem('mwn-mode'); } catch (e) { /* ignore */ }
setMode(savedMode || (window.innerWidth > 900 ? 'split' : 'edit'));
onInput();
uploadContent();
