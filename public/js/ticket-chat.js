(function () {
    var BASE_MS = 3000;
    var STEP_MS = 2000;
    var MAX_MS = 30000;

    function humanTime(iso) {
        var then = Date.parse(iso);
        if (!then) {
            return '';
        }

        var seconds = Math.max(1, Math.round((Date.now() - then) / 1000));
        var steps = [
            [60, 'second'],
            [60, 'minute'],
            [24, 'hour'],
            [7, 'day'],
            [4, 'week'],
            [12, 'month']
        ];
        var count = seconds;
        var name = 'year';

        for (var i = 0; i < steps.length; i++) {
            if (count < steps[i][0]) {
                name = steps[i][1];
                break;
            }
            count = Math.round(count / steps[i][0]);
        }

        if (name === 'year') {
            count = Math.max(1, Math.round(seconds / 31536000));
        }

        return count + ' ' + name + (count === 1 ? '' : 's') + ' ago';
    }

    function refreshTimes(root) {
        root.querySelectorAll('[data-ticket-time]').forEach(function (node) {
            var text = humanTime(node.getAttribute('data-ticket-time'));
            if (text) {
                node.textContent = text;
            }
        });
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function bodyHtml(value) {
        return escapeHtml(value).replace(/\n/g, '<br>');
    }

    function errorText(payload) {
        if (payload && payload.errors) {
            return Object.keys(payload.errors).reduce(function (lines, key) {
                return lines.concat(payload.errors[key]);
            }, []).join(' ');
        }

        return (payload && payload.message) || 'Could not send the message.';
    }

    function scrollToLatest(scrollEl, force) {
        if (!scrollEl) {
            return;
        }

        var distance = scrollEl.scrollHeight - scrollEl.scrollTop - scrollEl.clientHeight;
        if (force || distance < 120) {
            scrollEl.scrollTop = scrollEl.scrollHeight;
        }

        if (!force) {
            return;
        }

        var nodes = scrollEl.querySelectorAll('[data-message-id]');
        var last = nodes[nodes.length - 1];
        if (last && last.scrollIntoView) {
            last.scrollIntoView({ block: 'nearest' });
        }
    }

    function ensureList(root) {
        var list = root.querySelector('[data-ticket-list]');
        if (list) {
            return list;
        }

        var scrollEl = root.querySelector('[data-ticket-scroll]');
        if (!scrollEl) {
            return null;
        }

        var empty = scrollEl.querySelector('[data-ticket-empty]');
        if (empty) {
            empty.remove();
        }

        list = document.createElement(root.dataset.mode === 'admin' ? 'ul' : 'div');
        list.setAttribute('data-ticket-list', '');
        list.className = root.dataset.mode === 'admin' ? 'mb-0 pl-0' : 'chat-messages';
        scrollEl.appendChild(list);

        return list;
    }

    function attachmentHtml(message, attachment) {
        if (attachment.is_image) {
        if (message.mode === 'admin') {
            return '<a href="' + escapeHtml(attachment.url) + '" class="chat-attach-image-link" data-lightbox="' + escapeHtml(attachment.url) + '" data-title="' + escapeHtml(message.body) + '">' +
                '<img class="chat-attach-image" src="' + escapeHtml(attachment.url) + '" alt="' + escapeHtml(attachment.name || 'Image') + '"></a>';
        }

            return '<a href="' + escapeHtml(attachment.url) + '" data-lightbox="message-' + message.id + '" data-title="' + escapeHtml(message.body) + '" class="d-inline-block rounded overflow-hidden border" style="line-height: 0;">' +
                '<img src="' + escapeHtml(attachment.url) + '" alt="Attachment" class="img-fluid" style="max-height: 140px; max-width: 200px; object-fit: cover;"></a>';
        }

        if (message.mode === 'admin') {
            return '<a href="' + escapeHtml(attachment.url) + '" download class="chat-attach-file">' +
                '<span class="chat-attach-file__icon"><i class="la la-file"></i></span>' +
                '<span class="chat-attach-file__name">' + escapeHtml(attachment.name || 'Attachment') + '</span></a>';
        }

        var chipClass = message.mine ? 'text-white' : 'text-dark';
        var chipStyle = message.mine ? 'background: rgba(255,255,255,0.15);' : '';

        return '<a href="' + escapeHtml(attachment.url) + '" target="_blank" download class="d-flex align-items-center p-2 mb-2 rounded text-decoration-none ' + (message.mine ? '' : 'bg-light') + '" style="' + chipStyle + '">' +
            '<div class="file-icon mr-2 d-flex align-items-center justify-content-center bg-secondary text-white" style="width: 32px; height: 32px; border-radius: 6px;">' +
            '<i class="fa fa-file-alt" style="font-size: 14px;"></i></div>' +
            '<span class="text-truncate small ' + chipClass + '">' + escapeHtml(attachment.name) + '</span></a>';
    }

    function renderCustomer(message) {
        var images = [];
        var files = [];
        (message.attachments || []).forEach(function (attachment) {
            if (attachment.is_image) {
                images.push(attachmentHtml(message, attachment));
            } else {
                files.push(attachmentHtml(message, attachment));
            }
        });

        var imageBlock = images.length ? '<div class="mt-3 d-flex flex-wrap" style="gap: 8px;">' + images.join('') + '</div>' : '';
        var fileBlock = files.length ? '<div class="mt-3">' + files.join('') + '</div>' : '';
        var align = message.mine ? 'justify-content-end' : 'justify-content-start';
        var bubble = message.mine ? 'bg-primary text-white' : 'bg-white border';
        var radius = message.mine ? '18px 18px 4px 18px' : '18px 18px 18px 4px';
        var avatar = message.mine
            ? '<div class="avatar-sm bg-info text-white ml-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; border-radius: 50%; margin-top: 4px;"><i class="fa fa-user" style="font-size: 14px;"></i></div>'
            : '<div class="avatar-sm bg-secondary mr-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px; border-radius: 50%; margin-top: 4px;"><i class="fa fa-user-tie" style="font-size: 14px;"></i></div>';

        var node = document.createElement('div');
        node.className = 'd-flex mb-4 ' + align;
        node.setAttribute('data-message-id', String(message.id));
        node.innerHTML = (message.mine ? '' : avatar) +
            '<div class="message-content ' + (message.mine ? 'text-right' : '') + '" style="max-width: 75%;">' +
            '<div class="message-bubble p-3 ' + bubble + '" style="border-radius: ' + radius + '; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">' +
            '<p class="mb-0" style="white-space: pre-wrap; word-break: break-word;">' + bodyHtml(message.body) + '</p>' +
            imageBlock + fileBlock +
            '</div>' +
            '<small class="text-muted d-block mt-1 px-2" style="font-size: 11px;" data-ticket-time="' + escapeHtml(message.sent_at) + '">' + escapeHtml(message.time) + '</small>' +
            '</div>' +
            (message.mine ? avatar : '');

        return node;
    }

    function renderAdmin(message) {
        var images = [];
        var files = [];
        (message.attachments || []).forEach(function (attachment) {
            if (attachment.is_image) {
                images.push(attachmentHtml(message, attachment));
            } else {
                files.push(attachmentHtml(message, attachment));
            }
        });

        var body = message.body ? '<p class="text-left">' + bodyHtml(message.body) + '</p>' : '';
        var node = document.createElement('li');
        node.className = 'clearfix my-2';
        node.setAttribute('data-message-id', String(message.id));
        node.innerHTML = '<div class="message ' + (message.mine ? 'other-message float-right' : 'my-message') + '">' +
            body +
            images.join('') +
            files.join('') +
            '<small class="message-data-time text-muted font-italic" data-ticket-time="' + escapeHtml(message.sent_at) + '">' + escapeHtml(message.time) + '</small>' +
            '</div>';

        return node;
    }

    function sendRequest(url, data, token, onProgress) {
        return new Promise(function (resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', url);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('X-CSRF-TOKEN', token || '');
            xhr.upload.onprogress = function (event) {
                if (!onProgress || !event.lengthComputable || !event.total) {
                    return;
                }
                onProgress(Math.min(100, Math.round((event.loaded / event.total) * 100)));
            };
            xhr.onload = function () {
                var payload = {};
                try {
                    payload = JSON.parse(xhr.responseText || '{}');
                } catch (e) {
                    payload = {};
                }
                resolve({
                    ok: xhr.status >= 200 && xhr.status < 300,
                    status: xhr.status,
                    payload: payload
                });
            };
            xhr.onerror = function () {
                reject(new Error('network'));
            };
            xhr.send(data);
        });
    }

    function bindCompose(form) {
        if (!form || form.dataset.composeReady === '1') {
            return;
        }

        var input = form.querySelector('[data-compose-file]');
        var attach = form.querySelector('[data-compose-attach]');
        var picks = form.querySelector('[data-compose-picks]');
        var progress = form.querySelector('[data-compose-progress]');
        var bar = progress ? progress.querySelector('span') : null;
        var send = form.querySelector('[type="submit"]');
        var sendIcon = form.querySelector('[data-send-icon]');
        var spinner = form.querySelector('[data-send-spinner]');

        if (!input || !attach || !picks) {
            return;
        }

        form.dataset.composeReady = '1';

        var maxFiles = parseInt(form.getAttribute('data-compose-max') || '10', 10);
        var maxKb = parseInt(form.getAttribute('data-compose-max-kb') || '10240', 10);
        var iconClass = form.getAttribute('data-compose-icon') || 'la la-file';
        var picked = [];
        var urls = [];

        function errorBox() {
            return form.querySelector('[data-ticket-errors], [data-message-errors]');
        }

        function showComposeError(text) {
            var node = errorBox();
            if (node) {
                node.textContent = text || '';
            }
        }

        function limitText() {
            if (maxKb % 1024 === 0) {
                return (maxKb / 1024) + ' MB';
            }
            return maxKb + ' KB';
        }

        function formatSize(bytes) {
            if (bytes < 1024) {
                return bytes + ' B';
            }
            if (bytes < 1048576) {
                return Math.max(1, Math.round(bytes / 1024)) + ' KB';
            }
            return (bytes / 1048576).toFixed(1) + ' MB';
        }

        function revoke() {
            urls.forEach(function (url) {
                URL.revokeObjectURL(url);
            });
            urls = [];
        }

        function sync() {
            var transfer = new DataTransfer();
            picked.forEach(function (file) {
                transfer.items.add(file);
            });
            input.files = transfer.files;
        }

        function render() {
            revoke();
            picks.innerHTML = '';
            picked.forEach(function (file, index) {
                var chip = document.createElement('div');
                chip.className = 'chat-pick';
                var visual;
                if (file.type && file.type.indexOf('image/') === 0) {
                    var url = URL.createObjectURL(file);
                    urls.push(url);
                    visual = document.createElement('img');
                    visual.src = url;
                    visual.alt = '';
                } else {
                    visual = document.createElement('span');
                    visual.className = 'chat-pick__icon';
                    visual.innerHTML = '<i class="' + iconClass + '"></i>';
                }
                var meta = document.createElement('span');
                meta.className = 'chat-pick__meta';
                var name = document.createElement('span');
                name.className = 'chat-pick__name';
                name.textContent = file.name;
                var size = document.createElement('span');
                size.className = 'chat-pick__size';
                size.textContent = formatSize(file.size);
                meta.appendChild(name);
                meta.appendChild(size);
                var remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'chat-pick__remove';
                remove.setAttribute('aria-label', 'Remove ' + file.name);
                remove.textContent = '\u00d7';
                remove.addEventListener('click', function () {
                    if (form.dataset.sending === '1') {
                        return;
                    }
                    picked.splice(index, 1);
                    sync();
                    render();
                });
                chip.appendChild(visual);
                chip.appendChild(meta);
                chip.appendChild(remove);
                picks.appendChild(chip);
            });
        }

        attach.addEventListener('click', function () {
            if (form.dataset.sending === '1') {
                return;
            }
            input.click();
        });

        input.addEventListener('change', function () {
            if (form.dataset.sending === '1') {
                sync();
                return;
            }

            var incoming = Array.prototype.slice.call(input.files || []);
            input.value = '';

            var rejected = '';
            incoming.forEach(function (file) {
                var duplicate = picked.some(function (current) {
                    return current.name === file.name && current.size === file.size && current.lastModified === file.lastModified;
                });
                if (duplicate) {
                    return;
                }
                if (picked.length >= maxFiles) {
                    rejected = 'You can attach up to ' + maxFiles + ' files.';
                    return;
                }
                if (file.size > maxKb * 1024) {
                    rejected = file.name + ' is larger than ' + limitText() + '.';
                    return;
                }
                picked.push(file);
            });

            sync();
            render();
            showComposeError(rejected);
        });

        form._compose = {
            hasFiles: function () {
                return picked.length > 0;
            },
            setBusy: function (busy) {
                form.dataset.sending = busy ? '1' : '0';
                attach.disabled = !!busy;
                if (send) {
                    send.disabled = !!busy;
                }
                if (sendIcon) {
                    sendIcon.hidden = !!busy;
                }
                if (spinner) {
                    spinner.hidden = !busy;
                }
                picks.querySelectorAll('.chat-pick__remove').forEach(function (button) {
                    button.disabled = !!busy;
                });
                if (!busy && progress) {
                    progress.hidden = true;
                    if (bar) {
                        bar.style.width = '0';
                    }
                }
            },
            setProgress: function (pct) {
                if (progress) {
                    progress.hidden = false;
                }
                if (bar) {
                    bar.style.width = pct + '%';
                }
                picks.querySelectorAll('.chat-pick__size').forEach(function (node) {
                    node.textContent = 'Uploading ' + pct + '%';
                });
            },
            reset: function () {
                picked = [];
                input.value = '';
                sync();
                render();
                if (progress) {
                    progress.hidden = true;
                    if (bar) {
                        bar.style.width = '0';
                    }
                }
            }
        };
    }

    function start(root) {
        var list = root.querySelector('[data-ticket-list]');
        var form = root.querySelector('[data-ticket-form]');
        var errors = root.querySelector('[data-ticket-errors]');
        var scrollEl = root.querySelector('[data-ticket-scroll]');
        var mode = root.dataset.mode === 'admin' ? 'admin' : 'customer';
        var seen = {};
        var delay = BASE_MS;
        var generation = 0;
        var timer = null;
        var stopped = false;
        var inFlight = false;

        if (list) {
            list.querySelectorAll('[data-message-id]').forEach(function (node) {
                seen[node.getAttribute('data-message-id')] = true;
            });
        }

        refreshTimes(root);
        setInterval(function () {
            if (!document.hidden) {
                refreshTimes(root);
            }
        }, 5000);

        scrollToLatest(scrollEl, true);

        function lastId() {
            var maxId = 0;
            Object.keys(seen).forEach(function (id) {
                var numeric = parseInt(id, 10);
                if (numeric > maxId) {
                    maxId = numeric;
                }
            });
            return maxId;
        }

        function showError(text) {
            if (!errors) {
                return;
            }
            errors.textContent = text || '';
        }

        function append(messages, forceScroll) {
            var added = 0;
            var target = ensureList(root);
            if (!target) {
                return 0;
            }

            messages.forEach(function (message) {
                var id = String(message.id);
                if (seen[id]) {
                    return;
                }
                seen[id] = true;
                message.mode = mode;
                target.appendChild(mode === 'admin' ? renderAdmin(message) : renderCustomer(message));
                added += 1;
                if (message.mine) {
                    forceScroll = true;
                }
            });

            if (added > 0) {
                target.style.height = '';
                refreshTimes(root);
                scrollToLatest(scrollEl || target, forceScroll);
            }

            return added;
        }

        function schedule() {
            clearTimeout(timer);
            if (stopped || document.hidden) {
                return;
            }
            timer = setTimeout(poll, delay);
        }

        function poll() {
            if (stopped || inFlight || document.hidden) {
                return;
            }

            var seenGeneration = generation;
            inFlight = true;

            fetch(root.dataset.pollUrl + '?after=' + lastId(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            }).then(function (response) {
                if (response.status === 401 || response.status === 403 || response.status === 419) {
                    stopped = true;
                    return null;
                }
                if (!response.ok) {
                    throw new Error('poll failed');
                }
                return response.json();
            }).then(function (payload) {
                if (!payload) {
                    return;
                }
                var added = append(payload.messages || [], false);
                if (added > 0) {
                    delay = BASE_MS;
                } else if (seenGeneration === generation) {
                    delay = Math.min(delay + STEP_MS, MAX_MS);
                }
            }).catch(function () {
                if (seenGeneration === generation) {
                    delay = Math.min(delay + STEP_MS, MAX_MS);
                }
            }).then(function () {
                inFlight = false;
                schedule();
            });
        }

        function resetForm() {
            if (!form) {
                return;
            }
            var message = form.querySelector('[name="message"]');
            if (message) {
                message.value = '';
            }
            if (form._compose) {
                form._compose.reset();
            }
        }

        if (form) {
            bindCompose(form);
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                if (form.dataset.sending === '1') {
                    return;
                }
                showError('');

                var tokenField = form.querySelector('input[name="_token"]');
                var data = new FormData(form);
                var compose = form._compose;
                var hasFile = compose && compose.hasFiles();
                if (compose) {
                    compose.setBusy(true);
                }

                sendRequest(root.dataset.sendUrl, data, tokenField ? tokenField.value : '', function (pct) {
                    if (hasFile && compose) {
                        compose.setProgress(pct);
                    }
                }).then(function (result) {
                    if (!result.ok) {
                        if (result.status === 419) {
                            showError('Please refresh the page and try again.');
                        } else {
                            showError(errorText(result.payload));
                        }
                        return;
                    }
                    append(result.payload.message ? [result.payload.message] : [], true);
                    resetForm();
                    generation += 1;
                    delay = BASE_MS;
                    schedule();
                }).catch(function () {
                    showError('Could not send the message.');
                }).then(function () {
                    if (compose) {
                        compose.setBusy(false);
                    }
                });
            });
        }

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                clearTimeout(timer);
                return;
            }
            delay = BASE_MS;
            poll();
        });

        schedule();
    }

    window.TicketChat = { start: start };

    function boot() {
        var root = document.getElementById('ticket-chat');
        if (!root || root.dataset.ticketChatReady === '1') {
            return;
        }
        root.dataset.ticketChatReady = '1';
        start(root);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
