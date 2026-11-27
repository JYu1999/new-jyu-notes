/**
 * "/" commands for the Markdown editor (JYU-552). Pure helpers; the Alpine
 * behaviour that drives the menu lives in app.js (slashCommandBehavior).
 */

export const SLASH_COMMANDS = [
    { id: 'h2', label: '標題 H2', hint: '##', keywords: 'heading h2 title 標題 大標' },
    { id: 'h3', label: '標題 H3', hint: '###', keywords: 'heading h3 title 標題 小標' },
    { id: 'quote', label: '引用區塊', hint: '>', keywords: 'quote blockquote 引用 引言' },
    { id: 'code', label: '程式碼區塊', hint: '```', keywords: 'code codeblock 程式 程式碼' },
    { id: 'hr', label: '分隔線', hint: '---', keywords: 'divider hr line 分隔 分隔線' },
    { id: 'image', label: '圖片或影片', hint: '上傳', keywords: 'image photo picture video upload 圖片 照片 影片 上傳' },
    { id: 'youtube', label: 'YouTube 嵌入', hint: '網址', keywords: 'youtube video embed 影片 嵌入' },
    { id: 'mention', label: '引用文章或碎念', hint: '@', keywords: 'mention link @ 引用 文章 碎念 連結' },
];

/**
 * The "/query" being typed at the caret, or null. Only on an otherwise empty
 * line, so "/" inside a sentence or a URL never opens the menu.
 */
export function slashQueryAt(value, caret) {
    const lineStart = value.lastIndexOf('\n', caret - 1) + 1;
    const m = value.slice(lineStart, caret).match(/^\/([^\s/]{0,20})$/);
    if (!m) return null;
    const lineEnd = value.indexOf('\n', caret);
    if (value.slice(caret, lineEnd === -1 ? value.length : lineEnd).trim() !== '') return null;

    return { start: lineStart, query: m[1] };
}

export function filterCommands(query) {
    const q = query.trim().toLowerCase();
    if (!q) return SLASH_COMMANDS;

    return SLASH_COMMANDS.filter((c) => c.label.toLowerCase().includes(q) || c.keywords.toLowerCase().includes(q));
}

/**
 * Replace the "/query" (value[start, end)) with the command's Markdown.
 * Returns the new value and where the caret should go so typing can continue.
 * Commands that need more input (image, youtube, mention) only clear the line.
 */
export function applyCommand(id, value, start, end) {
    const before = value.slice(0, start);
    const after = value.slice(end);
    // A block needs a blank line above it when the previous line has text.
    const gap = before === '' || before.endsWith('\n\n') ? '' : '\n';
    const put = (text, caretOffset) => ({ value: before + text + after, caret: start + caretOffset });

    switch (id) {
        case 'h2': return put('## ', 3);
        case 'h3': return put('### ', 4);
        case 'quote': return put('> ', 2);
        case 'code': return put(`${gap}\`\`\`\n\n\`\`\``, gap.length + 4);
        // A blank line above keeps "---" from turning the previous line into a heading.
        case 'hr': return put(`${gap}---\n\n`, gap.length + 5);
        default: return put('', 0);
    }
}
