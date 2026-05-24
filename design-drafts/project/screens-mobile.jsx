// Mobile public-facing screens

const MobileStatusBar = () => (
  <div className="phone-statusbar">
    <span>09:41</span>
    <span style={{ display: 'inline-flex', gap: 6, alignItems: 'center', fontSize: 11 }}>
      <span>●●●●</span><span>5G</span><span>●●●●</span>
    </span>
  </div>
);

const MobileTopBar = ({ title, transparent }) => (
  <div style={{
    display: 'flex', alignItems: 'center', padding: '8px 16px 12px',
    borderBottom: transparent ? 'none' : '1px solid var(--line)',
    background: 'var(--paper)', position: 'relative', zIndex: 2,
  }}>
    <a className="wordmark" style={{
      fontFamily: 'var(--font-serif)', fontWeight: 500, fontSize: 18, letterSpacing: '-0.01em',
      display: 'flex', alignItems: 'baseline', gap: 4,
    }}>
      <span>JYu</span>
      <span style={{ color: 'var(--accent)' }}>.</span>
      <span style={{ color: 'var(--ink-3)', fontSize: 13, fontWeight: 400 }}>blog</span>
    </a>
    <div style={{ flex: 1 }} />
    <button className="icon-btn" style={{ width: 32, height: 32 }}><Icon name="search" size={16} /></button>
    <button className="icon-btn" style={{ width: 32, height: 32 }}><Icon name="menu" size={16} /></button>
  </div>
);

const MobileBottomNav = ({ active = 'home' }) => (
  <div style={{
    display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 4,
    borderTop: '1px solid var(--line)', padding: '8px 10px 14px',
    background: 'var(--paper)',
  }}>
    {[
      { k: 'home', icon: 'home', label: '首頁' },
      { k: 'posts', icon: 'file', label: '文章' },
      { k: 'tweets', icon: 'feather', label: '短文' },
      { k: 'search', icon: 'search', label: '搜尋' },
    ].map(item => (
      <button key={item.k} style={{
        display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 3,
        padding: 6, borderRadius: 8,
        color: active === item.k ? 'var(--accent)' : 'var(--ink-3)',
      }}>
        <Icon name={item.icon} size={18} />
        <span style={{ fontSize: 10, fontWeight: 600 }}>{item.label}</span>
      </button>
    ))}
  </div>
);

// === Mobile Home (mixed feed) ==================================================
const MobileHomeScreen = () => (
  <div className="jyu phone" data-screen-label="M01 · Mobile Home">
    <MobileStatusBar />
    <MobileTopBar />
    <div className="scrollable" style={{ flex: 1, padding: '20px 18px 24px' }}>
      {/* Hero */}
      <div style={{ marginBottom: 22 }}>
        <div style={{ fontFamily: 'var(--font-mono)', fontSize: 10, color: 'var(--ink-3)', letterSpacing: '0.08em', textTransform: 'uppercase', marginBottom: 8 }}>
          在台北寫程式 · est. 2018
        </div>
        <h1 className="serif" style={{ fontSize: 26, fontWeight: 500, lineHeight: 1.15, letterSpacing: '-0.015em' }}>
          我把這裡當作一個<em style={{ color: 'var(--accent)', fontStyle: 'italic' }}>慢思考</em>的地方。
        </h1>
      </div>

      {/* Filter pills */}
      <div style={{
        display: 'flex', gap: 4, marginBottom: 18, padding: 3, background: 'var(--paper-2)',
        borderRadius: 8, width: 'fit-content',
      }}>
        {['全部', '文章', '短文'].map((t, i) => (
          <button key={t} style={{
            padding: '5px 12px', fontSize: 12, fontWeight: 600, borderRadius: 5,
            background: i === 0 ? 'var(--card)' : 'transparent',
            color: i === 0 ? 'var(--ink)' : 'var(--ink-2)',
            boxShadow: i === 0 ? 'var(--shadow-sm)' : 'none',
          }}>{t}</button>
        ))}
      </div>

      {/* Featured post (card) */}
      <article style={{
        background: 'var(--card)', border: '1px solid var(--line)', borderRadius: 12,
        overflow: 'hidden', marginBottom: 14,
      }}>
        <div style={{ height: 140 }}>
          <CoverArt kind={POSTS[2].cover} hue={POSTS[2].hue} />
        </div>
        <div style={{ padding: 14 }}>
          <div style={{ display: 'flex', gap: 6, alignItems: 'center', fontFamily: 'var(--font-mono)', fontSize: 10, color: 'var(--ink-3)', marginBottom: 8 }}>
            <Category>{POSTS[2].category}</Category>
          </div>
          <h2 className="serif" style={{ fontSize: 19, fontWeight: 500, lineHeight: 1.2, letterSpacing: '-0.01em', marginBottom: 8 }}>
            {POSTS[2].title}
          </h2>
          <p className="serif" style={{ fontSize: 13.5, color: 'var(--ink-2)', lineHeight: 1.55, marginBottom: 10 }}>
            {POSTS[2].excerpt}
          </p>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, fontFamily: 'var(--font-mono)', fontSize: 10.5, color: 'var(--ink-3)', whiteSpace: 'nowrap' }}>
            <span>{POSTS[2].date.slice(5)}</span>
            <span>·</span>
            <span>{POSTS[2].readTime} min</span>
            <span>·</span>
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 3 }}>
              <Icon name="eye" size={10} /> {POSTS[2].views.toLocaleString()}
            </span>
          </div>
        </div>
      </article>

      {/* Inline tweet */}
      <div style={{
        marginBottom: 14, padding: '14px 16px',
        background: 'var(--paper-2)', borderRadius: 12, border: '1px dashed var(--line-2)',
      }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 8, fontFamily: 'var(--font-mono)', fontSize: 10.5, color: 'var(--ink-3)', whiteSpace: 'nowrap' }}>
          <Icon name="feather" size={11} />
          <span>短文 · {TWEETS[0].date.slice(5)}</span>
        </div>
        <p className="serif" style={{ fontSize: 15, lineHeight: 1.55 }}>{TWEETS[0].body}</p>
      </div>

      {/* More posts (compact) */}
      {POSTS.slice(0, 3).map(p => (
        <article key={p.id} style={{
          display: 'grid', gridTemplateColumns: '1fr 78px', gap: 12,
          padding: '14px 0', borderTop: '1px solid var(--line)',
        }}>
          <div style={{ minWidth: 0 }}>
            <div style={{ marginBottom: 6 }}><Category>{p.category}</Category></div>
            <h3 className="serif" style={{ fontSize: 15, fontWeight: 500, lineHeight: 1.25, marginBottom: 6 }}>
              {p.title}
            </h3>
            <div style={{ display: 'flex', gap: 6, alignItems: 'center', fontFamily: 'var(--font-mono)', fontSize: 10, color: 'var(--ink-3)', whiteSpace: 'nowrap' }}>
              <span>{p.date.slice(5)}</span>
              <span>·</span>
              <span>{p.readTime} min</span>
            </div>
          </div>
          <div style={{ width: 78, height: 78, borderRadius: 8, overflow: 'hidden' }}>
            <CoverArt kind={p.cover} hue={p.hue} />
          </div>
        </article>
      ))}

      <div style={{ height: 12 }} />
    </div>
    <MobileBottomNav active="home" />
  </div>
);

// === Mobile Post Detail ========================================================
const MobilePostDetailScreen = () => (
  <div className="jyu phone" data-screen-label="M02 · Mobile Post">
    <MobileStatusBar />
    {/* Custom sticky header with back */}
    <div style={{
      display: 'flex', alignItems: 'center', padding: '6px 14px 10px',
      borderBottom: '1px solid var(--line)', gap: 6, position: 'relative', zIndex: 2,
      background: 'var(--paper)',
    }}>
      <button className="icon-btn" style={{ width: 32, height: 32 }}><Icon name="chevron" size={16} stroke={2} /></button>
      <div style={{ flex: 1, minWidth: 0, fontSize: 12, fontFamily: 'var(--font-mono)', color: 'var(--ink-3)' }}>技術筆記 / 文章</div>
      <button className="icon-btn" style={{ width: 32, height: 32 }}><Icon name="bookmark" size={16} /></button>
      <button className="icon-btn" style={{ width: 32, height: 32 }}><Icon name="dots" size={16} /></button>
    </div>
    {/* Progress */}
    <div style={{ height: 2, background: 'var(--line)' }}>
      <div style={{ width: '42%', height: '100%', background: 'var(--accent)' }} />
    </div>

    <div className="scrollable" style={{ flex: 1 }}>
      <article style={{ padding: '20px 20px 32px' }}>
        <Category>技術筆記</Category>
        <h1 className="serif" style={{ fontSize: 26, fontWeight: 500, lineHeight: 1.18, letterSpacing: '-0.015em', marginTop: 10 }}>
          Tailwind v4 升級筆記：那些文件沒講清楚的事
        </h1>
        <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginTop: 12, fontFamily: 'var(--font-mono)', fontSize: 10.5, color: 'var(--ink-3)', flexWrap: 'wrap' }}>
          <span style={{ whiteSpace: 'nowrap' }}>2026-04-14</span><span>·</span>
          <span style={{ whiteSpace: 'nowrap' }}>9 min</span><span>·</span>
          <span style={{ display: 'inline-flex', alignItems: 'center', gap: 3, whiteSpace: 'nowrap' }}><Icon name="eye" size={10} /> 5,621</span>
        </div>

        <div style={{ height: 180, borderRadius: 8, overflow: 'hidden', margin: '20px 0' }}>
          <CoverArt kind="wave" hue={160} />
        </div>

        <div className="serif" style={{ fontSize: 16, lineHeight: 1.65, color: 'var(--ink)' }}>
          <p style={{ fontSize: 17, color: 'var(--ink-2)', marginBottom: 18, lineHeight: 1.55 }}>
            上週末我們把一個 8 萬行的 React 專案從 Tailwind v3 升到 v4。整個過程花了約 14 個小時，其中 11 個小時都在處理那些<em>文件沒寫</em>但會悄悄改變行為的東西。
          </p>
          <h2 className="serif" style={{ fontSize: 20, fontWeight: 500, marginTop: 24, marginBottom: 10 }}>1. CSS-first config 不是可選的</h2>
          <p style={{ marginBottom: 14 }}>
            v4 把 <code className="mono" style={{ background: 'var(--paper-2)', padding: '1px 5px', borderRadius: 4, fontSize: 13.5 }}>tailwind.config.js</code> 設為「相容模式」——它仍然能用，但有些新功能只在 CSS 設定生效。我們花了三個小時才意識到。
          </p>
          <div className="code" style={{ fontSize: 11.5 }}>
            <span className="c">{'/* v4 推薦寫法 */'}</span>{'\n'}
            <span className="k">@import</span> <span className="s">"tailwindcss"</span>;{'\n\n'}
            <span className="k">@theme</span> {'{'}{'\n'}
            {'  '}<span className="n">--color-accent</span>: <span className="s">oklch(0.55 0.18 25)</span>;{'\n'}
            {'}'}
          </div>
          <blockquote style={{ borderLeft: '3px solid var(--accent)', paddingLeft: 14, fontStyle: 'italic', color: 'var(--ink-2)', margin: '16px 0' }}>
            如果你的 design tokens 還住在 JS 物件裡，趁這次一起搬到 CSS 變數。
          </blockquote>
        </div>

        <div style={{ marginTop: 22, paddingTop: 16, borderTop: '1px solid var(--line)' }}>
          <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap', marginBottom: 14 }}>
            <Tag>Tailwind</Tag><Tag>React</Tag><Tag>升級</Tag>
          </div>
          <div style={{ display: 'flex', gap: 6 }}>
            <button className="btn btn-ghost" style={{ flex: 1, height: 36, fontSize: 12.5 }}><Icon name="heart" size={13} /> 喜歡</button>
            <button className="btn btn-ghost" style={{ flex: 1, height: 36, fontSize: 12.5 }}><Icon name="bookmark" size={13} /> 收藏</button>
            <button className="btn btn-ghost" style={{ flex: 1, height: 36, fontSize: 12.5 }}><Icon name="link" size={13} /></button>
          </div>
        </div>
      </article>
    </div>

    {/* Floating series nav */}
    <div style={{ borderTop: '1px solid var(--line)', padding: '10px 16px', background: 'var(--paper-2)', display: 'flex', alignItems: 'center', gap: 10 }}>
      <div style={{ flex: 1, minWidth: 0 }}>
        <div style={{ fontSize: 10, color: 'var(--ink-3)', fontFamily: 'var(--font-mono)', marginBottom: 2 }}>下一篇 →</div>
        <div className="serif" style={{ fontSize: 13, fontWeight: 500, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
          用 Bun 重寫 build pipeline 之後
        </div>
      </div>
      <button className="btn btn-accent" style={{ height: 34, fontSize: 12 }}>閱讀 <Icon name="arrowRight" size={12} /></button>
    </div>
  </div>
);

// === Mobile Tweet Timeline =====================================================
const MobileTweetTimelineScreen = () => (
  <div className="jyu phone" data-screen-label="M03 · Mobile Tweets">
    <MobileStatusBar />
    <MobileTopBar />

    <div className="scrollable" style={{ flex: 1, padding: '18px 18px 24px' }}>
      <div style={{ marginBottom: 14 }}>
        <h1 className="serif" style={{ fontSize: 24, fontWeight: 500, letterSpacing: '-0.01em', display: 'flex', alignItems: 'center', gap: 8 }}>
          <Icon name="feather" size={20} stroke={1.4} /> 短文
        </h1>
        <p className="serif" style={{ fontSize: 13.5, color: 'var(--ink-2)', marginTop: 4, lineHeight: 1.5 }}>
          想到什麼寫什麼。共 142 則。
        </p>
      </div>

      {/* Filter chip row */}
      <div style={{ display: 'flex', gap: 5, marginBottom: 16, overflowX: 'auto' }}>
        {['全部', '#寫作', '#CSS', '#工作流', '#雜記', '#讀書', '#AI'].map((t, i) => (
          <button key={t} className={`tag ${i === 0 ? 'solid' : ''}`} style={{ fontSize: 11.5, padding: '4px 10px' }}>
            {t}
          </button>
        ))}
      </div>

      {/* Day grouping */}
      <div style={{ fontFamily: 'var(--font-mono)', fontSize: 10, color: 'var(--ink-3)', textTransform: 'uppercase', letterSpacing: '0.08em', marginBottom: 4 }}>
        2026 · 五月 · 18 MON
      </div>
      <TweetCard tweet={TWEETS[0]} />

      <div style={{ fontFamily: 'var(--font-mono)', fontSize: 10, color: 'var(--ink-3)', textTransform: 'uppercase', letterSpacing: '0.08em', marginTop: 20, marginBottom: 4 }}>
        17 SUN
      </div>
      <TweetCard tweet={TWEETS[1]} />
      <TweetCard tweet={TWEETS[2]} />

      <div style={{ fontFamily: 'var(--font-mono)', fontSize: 10, color: 'var(--ink-3)', textTransform: 'uppercase', letterSpacing: '0.08em', marginTop: 20, marginBottom: 4 }}>
        16 SAT
      </div>
      <TweetCard tweet={TWEETS[3]} />

      <div style={{ height: 12 }} />
    </div>
    <MobileBottomNav active="tweets" />
  </div>
);

Object.assign(window, { MobileHomeScreen, MobilePostDetailScreen, MobileTweetTimelineScreen });
