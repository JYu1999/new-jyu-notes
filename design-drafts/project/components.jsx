// Shared components for JYu's Blog mockups

// === Icons (tiny stroke icons) =================================================
const Icon = ({ name, size = 16, stroke = 1.6 }) => {
  const p = {
    width: size, height: size, viewBox: '0 0 24 24', fill: 'none',
    stroke: 'currentColor', strokeWidth: stroke, strokeLinecap: 'round', strokeLinejoin: 'round',
  };
  const paths = {
    search: <><circle cx="11" cy="11" r="7" /><path d="m20 20-3.5-3.5" /></>,
    sun: <><circle cx="12" cy="12" r="4" /><path d="M12 3v1.5M12 19.5V21M3 12h1.5M19.5 12H21M5.6 5.6l1 1M17.4 17.4l1 1M5.6 18.4l1-1M17.4 6.6l1-1" /></>,
    moon: <path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5Z" />,
    globe: <><circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18" /></>,
    chevron: <path d="m6 9 6 6 6-6" />,
    chevronRight: <path d="m9 6 6 6-6 6" />,
    rss: <><path d="M4 11a9 9 0 0 1 9 9M4 4a16 16 0 0 1 16 16" /><circle cx="5" cy="19" r="1.5" /></>,
    eye: <><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" /><circle cx="12" cy="12" r="3" /></>,
    clock: <><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></>,
    arrowUp: <path d="M12 19V5M5 12l7-7 7 7" />,
    arrowDown: <path d="M12 5v14M5 12l7 7 7-7" />,
    arrowRight: <path d="M5 12h14M13 5l7 7-7 7" />,
    plus: <path d="M12 5v14M5 12h14" />,
    image: <><rect x="3" y="4" width="18" height="16" rx="2" /><circle cx="9" cy="10" r="1.5" /><path d="m21 16-5-5-9 9" /></>,
    bold: <path d="M7 5h6a4 4 0 0 1 0 8H7zM7 13h7a4 4 0 0 1 0 8H7z" />,
    italic: <path d="M19 4h-9M14 20H5M15 4 9 20" />,
    quote: <path d="M9 7H5a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h2v3H4M19 7h-4a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h2v3h-3" />,
    code: <path d="m16 18 6-6-6-6M8 6l-6 6 6 6" />,
    list: <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" />,
    link: <><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1" /><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1" /></>,
    edit: <><path d="M11 4H4v16h16v-7" /><path d="m18.5 2.5 3 3L12 15l-4 1 1-4Z" /></>,
    trash: <><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" /></>,
    eyeOff: <path d="m3 3 18 18M10.6 10.6a3 3 0 0 0 4.2 4.2M9.9 5.1A10 10 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-3.2 4.3M6.6 6.6A18 18 0 0 0 2 12s3.5 7 10 7a10 10 0 0 0 4.1-.9" />,
    dots: <><circle cx="12" cy="5" r="1.4" /><circle cx="12" cy="12" r="1.4" /><circle cx="12" cy="19" r="1.4" /></>,
    filter: <path d="M3 5h18l-7 9v6l-4-2v-4z" />,
    sort: <path d="M7 4v16m0 0-3-3m3 3 3-3M17 20V4m0 0-3 3m3-3 3 3" />,
    check: <path d="m4 12 5 5L20 6" />,
    menu: <path d="M3 6h18M3 12h18M3 18h18" />,
    x: <path d="M6 6l12 12M18 6 6 18" />,
    home: <path d="m3 11 9-8 9 8v9a2 2 0 0 1-2 2h-5v-7H10v7H5a2 2 0 0 1-2-2z" />,
    file: <><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" /><path d="M14 3v5h5" /></>,
    feather: <><path d="M20.2 4 8.5 15.7a4.7 4.7 0 1 0 6.6 6.6L19 18.4" /><path d="M13 6.5 19 12M3 21l9-9" /></>,
    tag: <><path d="M19 13 13 19a2 2 0 0 1-3 0L3 12V3h9l7 7a2 2 0 0 1 0 3z" /><circle cx="8" cy="8" r="1.5" /></>,
    layers: <path d="M12 3 2 8l10 5 10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />,
    photo: <><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="9" cy="9" r="2" /><path d="m21 16-4-4-9 9" /></>,
    send: <path d="M22 2 11 13M22 2l-7 20-4-9-9-4z" />,
    cog: <><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 0 1-4 0v-.1a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 0 1 0-4h.1a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3h0a1.6 1.6 0 0 0 1-1.5V3a2 2 0 0 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8v0a1.6 1.6 0 0 0 1.5 1H21a2 2 0 0 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z" /></>,
    folder: <path d="M3 7a2 2 0 0 1 2-2h4l2 3h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />,
    grid: <><rect x="3" y="3" width="7" height="7" /><rect x="14" y="3" width="7" height="7" /><rect x="3" y="14" width="7" height="7" /><rect x="14" y="14" width="7" height="7" /></>,
    listView: <><line x1="8" y1="6" x2="21" y2="6" /><line x1="8" y1="12" x2="21" y2="12" /><line x1="8" y1="18" x2="21" y2="18" /><circle cx="4" cy="6" r="1" /><circle cx="4" cy="12" r="1" /><circle cx="4" cy="18" r="1" /></>,
    save: <><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" /><path d="M17 21v-8H7v8M7 3v5h8" /></>,
    heart: <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21l7.8-7.4 1-1.1a5.5 5.5 0 0 0 0-7.8z" />,
    bookmark: <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z" />,
    chat: <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />,
  };
  return <svg {...p}>{paths[name] || null}</svg>;
};

// === Sample content ============================================================
const POSTS = [
  {
    id: 1, title: '在小型 SaaS 公司，我學到的三件關於設計系統的事',
    excerpt: '從零開始建立一個內部設計系統並不浪漫——但它徹底改變了我們交付產品的方式。這篇文章整理了過去十八個月的觀察與失誤。',
    tags: ['設計系統', 'SaaS', '反思'], category: '設計筆記',
    date: '2026-05-12', updated: '2026-05-15', views: 1842, readTime: 7,
    cover: 'grid', hue: 14,
  },
  {
    id: 2, title: '為什麼我把 Notion 換成了純文字筆記',
    excerpt: '七年的工具切換之後，我又回到了 plain text。Markdown、Git、終端機——以及為什麼這些古老的東西仍然贏。',
    tags: ['工具', 'Markdown', '生產力'], category: '工作流',
    date: '2026-04-28', updated: '2026-04-28', views: 3107, readTime: 5,
    cover: 'stack', hue: 200,
  },
  {
    id: 3, title: 'Tailwind v4 升級筆記：那些文件沒講清楚的事',
    excerpt: '我們把一個 8 萬行的 React 專案從 v3 升到 v4。整個過程花了一個週末，踩了五個地雷。本文是給未來自己的備忘錄。',
    tags: ['Tailwind', 'React', '升級'], category: '技術筆記',
    date: '2026-04-14', updated: '2026-04-20', views: 5621, readTime: 9,
    cover: 'wave', hue: 160,
  },
  {
    id: 4, title: '一個關於回饋的小實驗',
    excerpt: '連續三十天，每天早上問同事一個結構化的問題。沒有「順便」、沒有「等一下再說」。我學到的比過去三年都多。',
    tags: ['團隊', '溝通', '實驗'], category: '工作流',
    date: '2026-03-30', updated: '2026-04-02', views: 928, readTime: 4,
    cover: 'circles', hue: 30,
  },
  {
    id: 5, title: 'PostgreSQL 的 LISTEN/NOTIFY 救了我們的 webhook 架構',
    excerpt: '在轉向 Redis Pub/Sub 之前，我們試了一個簡單的方案——直接用 Postgres。三個月後，它仍然是我們的選擇。',
    tags: ['PostgreSQL', '後端', '架構'], category: '技術筆記',
    date: '2026-03-08', updated: '2026-03-08', views: 4280, readTime: 11,
    cover: 'lines', hue: 220,
  },
  {
    id: 6, title: '寫作的最大障礙不是時間，是「不知道想說什麼」',
    excerpt: '為什麼我們會在打開編輯器的那一刻僵住？關於思考的三種模式，以及我如何用一個簡單的儀式繞過它。',
    tags: ['寫作', '思考'], category: '設計筆記',
    date: '2026-02-22', updated: '2026-02-22', views: 1503, readTime: 6,
    cover: 'dots', hue: 280,
  },
];

const TWEETS = [
  { id: 't1', body: '寫了兩個月的長文，發現真正想表達的核心其實只需要三句話。剩下的都是說服自己的腳手架。', tags: ['寫作'], date: '2026-05-18', time: '09:14', img: false },
  { id: 't2', body: 'PSA：如果你的 CSS 還在用 `@media (prefers-color-scheme)`，去看看 `light-dark()` 函數。瀏覽器支援已經夠好了。', tags: ['CSS', '前端'], date: '2026-05-17', time: '23:02', img: false, code: 'color: light-dark(black, white);' },
  { id: 't3', body: '今天的小發現：在編輯器裡把 "TODO" 換成 "FIXME:" 之後，過去一週的待辦項減少了三分之一。文字真的會改變行為。', tags: ['工作流'], date: '2026-05-17', time: '11:47', img: false },
  { id: 't4', body: '半夜在咖啡店改 design system tokens。靈感最好的時候從來不是上班的時候，這件事讓我有點生氣。', tags: ['雜記'], date: '2026-05-16', time: '01:23', img: true },
  { id: 't5', body: '讀完《How Big Things Get Done》。最大的收穫不是它的方法論，而是它對「為什麼大型專案失敗」的歸納——通常不是執行問題，是想清楚之前就動手了。', tags: ['讀書', '專案管理'], date: '2026-05-15', time: '20:11', img: false },
  { id: 't6', body: '把所有 ChatGPT 對話的 export 餵給一個本地腳本，自動生成週報草稿。準確率約 80%，但省下的時間夠我看一整集 podcast。', tags: ['AI', '生產力'], date: '2026-05-14', time: '17:30', img: false },
];

const ALL_TAGS = ['設計系統', 'SaaS', '反思', '工具', 'Markdown', '生產力', 'Tailwind', 'React', '升級', '團隊', '溝通', '實驗', 'PostgreSQL', '後端', '架構', '寫作', '思考', 'CSS', '前端', '工作流', '雜記', '讀書', '專案管理', 'AI'];
const ALL_CATEGORIES = [
  { name: '設計筆記', count: 12 },
  { name: '工作流', count: 8 },
  { name: '技術筆記', count: 24 },
  { name: '讀書摘要', count: 6 },
  { name: '隨筆', count: 19 },
];

// === Cover art (placeholder SVG patterns) ======================================
const CoverArt = ({ kind = 'grid', hue = 14, size = 'card' }) => {
  const big = size === 'hero';
  const h = hue;
  const bg = `oklch(0.85 0.06 ${h})`;
  const fg = `oklch(0.55 0.12 ${h})`;
  const accent = `oklch(0.40 0.14 ${h})`;
  return (
    <div className="cover" style={{ width: '100%', height: '100%', background: bg }}>
      <svg viewBox="0 0 200 140" preserveAspectRatio="xMidYMid slice">
        {kind === 'grid' && (
          <g stroke={fg} strokeWidth="0.7" opacity="0.6">
            {Array.from({ length: 12 }).map((_, i) => <line key={'v' + i} x1={i * 20} y1="0" x2={i * 20} y2="140" />)}
            {Array.from({ length: 8 }).map((_, i) => <line key={'h' + i} x1="0" y1={i * 20} x2="200" y2={i * 20} />)}
            <rect x="60" y="40" width="80" height="60" fill={accent} opacity="0.7" />
          </g>
        )}
        {kind === 'stack' && (
          <g>
            {[0, 1, 2, 3].map(i => (
              <rect key={i} x={30 + i * 6} y={20 + i * 8} width={120} height={20} rx={3} fill={accent} opacity={0.35 + i * 0.15} />
            ))}
          </g>
        )}
        {kind === 'wave' && (
          <g>
            <path d="M0 90 Q 50 40, 100 90 T 200 90 V140 H0 Z" fill={accent} opacity="0.7" />
            <path d="M0 110 Q 50 60, 100 110 T 200 110 V140 H0 Z" fill={fg} opacity="0.5" />
          </g>
        )}
        {kind === 'circles' && (
          <g fill={accent} opacity="0.55">
            <circle cx="60" cy="70" r="40" />
            <circle cx="130" cy="70" r="30" fill={fg} opacity="0.8" />
            <circle cx="100" cy="90" r="18" fill={accent} />
          </g>
        )}
        {kind === 'lines' && (
          <g stroke={accent} strokeWidth="2" fill="none">
            {Array.from({ length: 20 }).map((_, i) => (
              <path key={i} d={`M0 ${20 + i * 6} Q 100 ${5 + i * 4}, 200 ${20 + i * 6}`} opacity={0.15 + (i % 5) * 0.1} />
            ))}
          </g>
        )}
        {kind === 'dots' && (
          <g fill={accent}>
            {Array.from({ length: 8 }).map((_, r) => Array.from({ length: 12 }).map((_, c) => (
              <circle key={r + '-' + c} cx={10 + c * 18} cy={10 + r * 18} r={1.5 + Math.abs(Math.sin(r + c)) * 3} opacity="0.6" />
            )))}
          </g>
        )}
      </svg>
    </div>
  );
};

// === Building blocks ===========================================================
const Tag = ({ children, solid }) => <span className={`tag ${solid ? 'solid' : ''}`}>#{children}</span>;

const Category = ({ children }) => <span className="category">{children}</span>;

const Pill = ({ status }) => {
  const label = { published: '已發布', draft: '草稿', hidden: '隱藏', deleted: '已刪除' }[status];
  return <span className={`pill ${status}`}><span className="dot" />{label}</span>;
};

const PostMeta = ({ post, showCategory = true }) => (
  <div className="post-meta">
    {showCategory && <Category>{post.category}</Category>}
    <span className="dot-sep" />
    <span>{post.date}</span>
    <span className="dot-sep" />
    <span>{post.readTime} min read</span>
    <span className="dot-sep" />
    <span style={{ display: 'inline-flex', alignItems: 'center', gap: 3 }}><Icon name="eye" size={11} /> {post.views.toLocaleString()}</span>
  </div>
);

const PostCard = ({ post, compact }) => (
  <article className="post-card" style={compact ? { padding: '18px 0' } : null}>
    <div>
      <PostMeta post={post} />
      <h3 className="post-title serif">{post.title}</h3>
      <p className="post-excerpt">{post.excerpt}</p>
      <div className="post-tags">
        {post.tags.map(t => <Tag key={t}>{t}</Tag>)}
      </div>
    </div>
    <div className="post-cover">
      <CoverArt kind={post.cover} hue={post.hue} />
    </div>
  </article>
);

const TweetCard = ({ tweet }) => (
  <article className="tweet-card">
    <div className="tweet-meta">
      <span>{tweet.date}</span>
      <span>·</span>
      <span>{tweet.time}</span>
    </div>
    <div className="tweet-body serif">
      <p>{tweet.body}</p>
      {tweet.code && (
        <div className="code" style={{ marginTop: 10, fontSize: 11.5 }}>
          <span className="n">{tweet.code}</span>
        </div>
      )}
      {tweet.img && (
        <div style={{ marginTop: 12, height: 160, borderRadius: 8, overflow: 'hidden' }}>
          <CoverArt kind="lines" hue={30} />
        </div>
      )}
    </div>
    <div className="tweet-tags">
      {tweet.tags.map(t => <Tag key={t}>{t}</Tag>)}
    </div>
  </article>
);

// === Public site chrome ========================================================
const TopBar = ({ active = 'home', lang = 'zh', theme = 'light', onToggleTheme, onToggleLang }) => (
  <header className="topbar">
    <a className="wordmark" href="#">
      <span>JYu</span>
      <span className="dot">.</span>
      <span style={{ color: 'var(--ink-3)', fontSize: 16, fontWeight: 400 }}>blog</span>
    </a>
    <nav>
      <a className={active === 'home' ? 'active' : ''}>首頁</a>
      <a className={active === 'posts' ? 'active' : ''}>文章</a>
      <a className={active === 'tweets' ? 'active' : ''}>短文</a>
      <a className={active === 'cats' ? 'active' : ''}>系列</a>
      <a className={active === 'about' ? 'active' : ''}>關於</a>
    </nav>
    <div className="spacer" />
    <div className="search-pill">
      <Icon name="search" size={13} />
      <span>搜尋文章與短文</span>
      <kbd>⌘K</kbd>
    </div>
    <button className="icon-btn" onClick={onToggleLang} title="Language">
      <Icon name="globe" size={16} />
    </button>
    <button className="icon-btn" onClick={onToggleTheme} title="Theme">
      <Icon name={theme === 'dark' ? 'sun' : 'moon'} size={16} />
    </button>
  </header>
);

// === Admin chrome ==============================================================
const AdminSidebar = ({ active = 'posts' }) => (
  <aside className="sidebar" style={{ width: 220, height: '100%' }}>
    <div style={{ display: 'flex', alignItems: 'center', gap: 10, padding: '6px 10px 16px' }}>
      <div style={{
        width: 28, height: 28, borderRadius: 6, background: 'var(--ink)',
        color: 'var(--paper)', display: 'flex', alignItems: 'center', justifyContent: 'center',
        fontFamily: 'var(--font-serif)', fontWeight: 600, fontSize: 15,
      }}>J</div>
      <div>
        <div style={{ fontSize: 13, fontWeight: 600 }}>JYu's Blog</div>
        <div style={{ fontFamily: 'var(--font-mono)', fontSize: 10, color: 'var(--ink-3)' }}>admin v2.4</div>
      </div>
    </div>
    <div className="group-label">內容</div>
    <a className={active === 'posts' ? 'active' : ''}><Icon name="file" size={14} />文章 <span className="count">68</span></a>
    <a className={active === 'tweets' ? 'active' : ''}><Icon name="feather" size={14} />短文 <span className="count">142</span></a>
    <a className={active === 'drafts' ? 'active' : ''}><Icon name="edit" size={14} />草稿 <span className="count">5</span></a>
    <a className={active === 'trash' ? 'active' : ''}><Icon name="trash" size={14} />回收桶 <span className="count">3</span></a>
    <div className="group-label">分類</div>
    <a className={active === 'tags' ? 'active' : ''}><Icon name="tag" size={14} />Tag <span className="count">{ALL_TAGS.length}</span></a>
    <a className={active === 'cats' ? 'active' : ''}><Icon name="layers" size={14} />Category <span className="count">{ALL_CATEGORIES.length}</span></a>
    <div className="group-label">資料</div>
    <a><Icon name="photo" size={14} />媒體庫</a>
    <a><Icon name="cog" size={14} />設定</a>
    <div style={{ flex: 1 }} />
    <a style={{ borderTop: '1px solid var(--line)', marginTop: 8, paddingTop: 14 }}>
      <Icon name="arrowRight" size={14} />前台預覽
    </a>
  </aside>
);

// Expose
Object.assign(window, {
  Icon, CoverArt, Tag, Category, Pill, PostMeta, PostCard, TweetCard, TopBar, AdminSidebar,
  POSTS, TWEETS, ALL_TAGS, ALL_CATEGORIES,
});
