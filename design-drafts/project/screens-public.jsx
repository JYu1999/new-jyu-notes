// Public-facing screens for JYu's Blog

// === Home (mixed feed: hero post + recent posts + tweets sidebar) ==============
const HomeScreen = () => (
  <div className="jyu" data-screen-label="01 Home">
    <TopBar active="home" />
    <div className="scrollable" style={{ height: 'calc(100% - 71px)', padding: '36px 56px 80px' }}>
      <div style={{ maxWidth: 1280, margin: '0 auto' }}>

        {/* Hero / about strip */}
        <section style={{ display: 'grid', gridTemplateColumns: '1fr auto', alignItems: 'end', gap: 40, paddingBottom: 32, borderBottom: '1px solid var(--line)' }}>
          <div>
            <div style={{ fontFamily: 'var(--font-mono)', fontSize: 11, color: 'var(--ink-3)', letterSpacing: '0.08em', textTransform: 'uppercase', marginBottom: 12 }}>
              在台北寫程式 · 設計與工程之間 · est. 2018
            </div>
            <h1 className="serif" style={{ fontSize: 44, fontWeight: 500, lineHeight: 1.1, letterSpacing: '-0.015em', maxWidth: '18ch', color: 'var(--ink)' }}>
              我把這裡當作一個<em style={{ color: 'var(--accent)', fontStyle: 'italic' }}>慢思考</em>的地方。
            </h1>
            <p className="serif" style={{ fontSize: 17, color: 'var(--ink-2)', marginTop: 14, maxWidth: '52ch', lineHeight: 1.6 }}>
              長文紀錄工作筆記與想法。<span style={{ color: 'var(--ink-3)' }}>短文</span>則像是隨手丟下的便條紙——通常更不負責任，但更接近當下的我。
            </p>
          </div>
          <div style={{ display: 'flex', gap: 8 }}>
            <button className="btn btn-ghost"><Icon name="rss" size={13} /> RSS</button>
            <button className="btn btn-primary">訂閱電子報</button>
          </div>
        </section>

        {/* Two-column: featured posts + tweet ticker */}
        <section style={{ display: 'grid', gridTemplateColumns: '1fr 320px', gap: 56, marginTop: 36 }}>
          <div>
            <div style={{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between', marginBottom: 8 }}>
              <h2 className="serif" style={{ fontSize: 20, fontWeight: 500 }}>最近的文章</h2>
              <a style={{ fontFamily: 'var(--font-mono)', fontSize: 11.5, color: 'var(--ink-3)' }}>查看全部 ({POSTS.length + 62}) →</a>
            </div>
            {POSTS.slice(0, 4).map(p => <PostCard key={p.id} post={p} />)}
          </div>

          <aside style={{ position: 'sticky', top: 0, alignSelf: 'start' }}>
            <div style={{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between', marginBottom: 14 }}>
              <h2 className="serif" style={{ fontSize: 16, fontWeight: 500, display: 'flex', alignItems: 'center', gap: 8 }}>
                <Icon name="feather" size={14} />最新短文
              </h2>
              <a style={{ fontFamily: 'var(--font-mono)', fontSize: 11, color: 'var(--ink-3)' }}>時間軸 →</a>
            </div>
            <div>{TWEETS.slice(0, 4).map(t => <TweetCard key={t.id} tweet={t} />)}</div>

            <div style={{ marginTop: 28, padding: 16, background: 'var(--paper-2)', borderRadius: 10, border: '1px solid var(--line)' }}>
              <div style={{ fontFamily: 'var(--font-mono)', fontSize: 10.5, color: 'var(--ink-3)', textTransform: 'uppercase', letterSpacing: '0.08em', marginBottom: 10 }}>熱門 Tag</div>
              <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
                {['設計系統', 'React', 'PostgreSQL', 'Tailwind', '寫作', '工作流', 'CSS', '反思'].map(t => <Tag key={t}>{t}</Tag>)}
              </div>
            </div>
          </aside>
        </section>
      </div>
    </div>
  </div>
);

// === Post list (with filters, sort, dense view) ================================
const PostListScreen = () => (
  <div className="jyu" data-screen-label="02 Post List">
    <TopBar active="posts" />
    <div className="scrollable" style={{ height: 'calc(100% - 71px)', padding: '36px 56px 80px' }}>
      <div style={{ maxWidth: 1280, margin: '0 auto' }}>

        <div style={{ display: 'flex', alignItems: 'baseline', justifyContent: 'space-between', borderBottom: '1px solid var(--line)', paddingBottom: 18 }}>
          <div>
            <h1 className="serif" style={{ fontSize: 36, fontWeight: 500, letterSpacing: '-0.01em' }}>所有文章</h1>
            <div style={{ fontFamily: 'var(--font-mono)', fontSize: 12, color: 'var(--ink-3)', marginTop: 6 }}>
              68 篇 · 從 2018 到現在
            </div>
          </div>
          <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
            <div style={{ fontFamily: 'var(--font-mono)', fontSize: 11, color: 'var(--ink-3)', marginRight: 8 }}>排序</div>
            <button className="btn btn-ghost" style={{ height: 30, fontSize: 12.5 }}>最近更新 <Icon name="chevron" size={12} /></button>
            <div style={{ width: 1, height: 20, background: 'var(--line)', margin: '0 8px' }} />
            <button className="btn btn-ghost" style={{ height: 30, fontSize: 12.5 }}><Icon name="grid" size={12} /></button>
            <button className="btn btn-ghost" style={{ height: 30, fontSize: 12.5, background: 'var(--paper-2)' }}><Icon name="listView" size={12} /></button>
          </div>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: '1fr 240px', gap: 56, marginTop: 28 }}>
          <div>
            {POSTS.map(p => <PostCard key={p.id} post={p} />)}
          </div>
          <aside>
            <div style={{ fontFamily: 'var(--font-mono)', fontSize: 10.5, color: 'var(--ink-3)', textTransform: 'uppercase', letterSpacing: '0.08em', marginBottom: 12 }}>系列 Categories</div>
            <ul style={{ marginBottom: 28 }}>
              {ALL_CATEGORIES.map(c => (
                <li key={c.name} style={{ display: 'flex', justifyContent: 'space-between', padding: '7px 0', borderBottom: '1px solid var(--line)', fontSize: 13.5 }}>
                  <span>{c.name}</span>
                  <span style={{ fontFamily: 'var(--font-mono)', fontSize: 11, color: 'var(--ink-3)' }}>{c.count}</span>
                </li>
              ))}
            </ul>
            <div style={{ fontFamily: 'var(--font-mono)', fontSize: 10.5, color: 'var(--ink-3)', textTransform: 'uppercase', letterSpacing: '0.08em', marginBottom: 12 }}>篩選 Tag</div>
            <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
              {ALL_TAGS.slice(0, 16).map((t, i) => <Tag key={t} solid={i === 1}>{t}</Tag>)}
            </div>
            <a style={{ display: 'inline-block', marginTop: 12, fontSize: 12, color: 'var(--ink-3)' }}>顯示全部 {ALL_TAGS.length} 個 tag →</a>
          </aside>
        </div>
      </div>
    </div>
  </div>
);

// === Post detail (reading view) ================================================
const PostDetailScreen = () => (
  <div className="jyu" data-screen-label="03 Post Detail">
    <TopBar active="posts" />
    <div className="scrollable" style={{ height: 'calc(100% - 71px)' }}>
      {/* Reading progress bar */}
      <div style={{ position: 'sticky', top: 0, height: 2, background: 'var(--line)', zIndex: 5 }}>
        <div style={{ width: '34%', height: '100%', background: 'var(--accent)' }} />
      </div>

      <article style={{ maxWidth: 720, margin: '0 auto', padding: '56px 32px 80px' }}>
        <Category>技術筆記</Category>
        <h1 className="serif" style={{ fontSize: 42, fontWeight: 500, lineHeight: 1.15, letterSpacing: '-0.015em', marginTop: 16 }}>
          Tailwind v4 升級筆記：那些文件沒講清楚的事
        </h1>
        <div style={{ display: 'flex', alignItems: 'center', gap: 14, marginTop: 18, fontFamily: 'var(--font-mono)', fontSize: 11.5, color: 'var(--ink-3)' }}>
          <span>2026-04-14 · 更新於 04-20</span>
          <span>·</span>
          <span>9 min read</span>
          <span>·</span>
          <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4 }}><Icon name="eye" size={11} /> 5,621 views</span>
        </div>

        <div style={{ height: 280, borderRadius: 8, overflow: 'hidden', margin: '32px 0' }}>
          <CoverArt kind="wave" hue={160} />
        </div>

        <div className="serif" style={{ fontSize: 18, lineHeight: 1.7, color: 'var(--ink)' }}>
          <p style={{ fontSize: 20, color: 'var(--ink-2)', marginBottom: 24, lineHeight: 1.55 }}>
            上週末我們把一個 8 萬行的 React 專案從 Tailwind v3 升到 v4。整個過程花了約 14 個小時，其中 11 個小時都在處理那些<em>文件沒寫</em>但會悄悄改變行為的東西。
          </p>
          <p style={{ marginBottom: 20 }}>
            這篇文章不是 v4 的入門指南，而是一份給未來自己看的備忘錄。如果你也在準備升級，希望能省下你一些頭髮。
          </p>

          <h2 className="serif" style={{ fontSize: 26, fontWeight: 500, marginTop: 36, marginBottom: 14, letterSpacing: '-0.01em' }}>1. CSS-first config 不是可選的</h2>
          <p style={{ marginBottom: 20 }}>
            v4 把 <code className="mono" style={{ background: 'var(--paper-2)', padding: '1px 6px', borderRadius: 4, fontSize: 15 }}>tailwind.config.js</code> 設為「相容模式」——它仍然能用，但有些新功能（例如 <code className="mono" style={{ background: 'var(--paper-2)', padding: '1px 6px', borderRadius: 4, fontSize: 15 }}>@theme</code> 的 token 解析）只在 CSS 設定生效。我們花了三個小時才意識到這件事。
          </p>

          <div className="code" style={{ marginBottom: 20 }}>
            <span className="c">{'/* v4 推薦寫法：直接寫在 CSS 裡 */'}</span>{'\n'}
            <span className="k">@import</span> <span className="s">"tailwindcss"</span>;{'\n\n'}
            <span className="k">@theme</span> {'{'}{'\n'}
            {'  '}<span className="n">--color-accent</span>: <span className="s">oklch(0.55 0.18 25)</span>;{'\n'}
            {'  '}<span className="n">--font-serif</span>: <span className="s">"Newsreader"</span>, serif;{'\n'}
            {'}'}
          </div>

          <blockquote style={{ borderLeft: '3px solid var(--accent)', paddingLeft: 20, fontStyle: 'italic', color: 'var(--ink-2)', marginBottom: 20 }}>
            如果你的 design tokens 還住在 JS 物件裡，趁這次一起搬到 CSS 變數，<br />未來的你會感謝你。
          </blockquote>

          <h2 className="serif" style={{ fontSize: 26, fontWeight: 500, marginTop: 36, marginBottom: 14, letterSpacing: '-0.01em' }}>2. <code className="mono" style={{ fontSize: 22 }}>@apply</code> 在 component layer 內的行為變了</h2>
          <p style={{ marginBottom: 20 }}>
            這個是地雷區。v3 的時候你可以在 <code className="mono" style={{ background: 'var(--paper-2)', padding: '1px 6px', borderRadius: 4, fontSize: 15 }}>@layer components</code> 內任意 @apply 任何 utility，包括 modifier。v4 把這個限制收緊了——你需要明確 import…
          </p>
        </div>

        {/* End of article meta */}
        <div style={{ marginTop: 48, paddingTop: 28, borderTop: '1px solid var(--line)', display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
          <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
            <Tag>Tailwind</Tag><Tag>React</Tag><Tag>升級</Tag>
          </div>
          <div style={{ display: 'flex', gap: 6 }}>
            <button className="btn btn-ghost" style={{ height: 32, padding: '0 10px' }}><Icon name="heart" size={13} /> 喜歡</button>
            <button className="btn btn-ghost" style={{ height: 32, padding: '0 10px' }}><Icon name="bookmark" size={13} /> 收藏</button>
            <button className="btn btn-ghost" style={{ height: 32, padding: '0 10px' }}><Icon name="link" size={13} /> 分享</button>
          </div>
        </div>

        {/* Series nav */}
        <div style={{ marginTop: 28, padding: 18, background: 'var(--paper-2)', borderRadius: 10, border: '1px solid var(--line)' }}>
          <div style={{ fontFamily: 'var(--font-mono)', fontSize: 10.5, color: 'var(--ink-3)', textTransform: 'uppercase', letterSpacing: '0.08em', marginBottom: 10 }}>系列 · 技術筆記</div>
          <div style={{ display: 'flex', justifyContent: 'space-between', gap: 16 }}>
            <a style={{ flex: 1 }}>
              <div style={{ fontSize: 11, color: 'var(--ink-3)', marginBottom: 4 }}>← 上一篇</div>
              <div className="serif" style={{ fontSize: 15, color: 'var(--ink) ' }}>PostgreSQL 的 LISTEN/NOTIFY 救了我們的 webhook 架構</div>
            </a>
            <div style={{ width: 1, background: 'var(--line)' }} />
            <a style={{ flex: 1, textAlign: 'right' }}>
              <div style={{ fontSize: 11, color: 'var(--ink-3)', marginBottom: 4 }}>下一篇 →</div>
              <div className="serif" style={{ fontSize: 15 }}>用 Bun 重寫 build pipeline 之後</div>
            </a>
          </div>
        </div>
      </article>
    </div>
  </div>
);

// === Tweet timeline ===========================================================
const TweetTimelineScreen = () => (
  <div className="jyu" data-screen-label="04 Tweet Timeline">
    <TopBar active="tweets" />
    <div className="scrollable" style={{ height: 'calc(100% - 71px)', padding: '36px 56px 80px' }}>
      <div style={{ maxWidth: 760, margin: '0 auto' }}>
        <div style={{ borderBottom: '1px solid var(--line)', paddingBottom: 20, marginBottom: 24 }}>
          <h1 className="serif" style={{ fontSize: 36, fontWeight: 500, letterSpacing: '-0.01em', display: 'flex', alignItems: 'center', gap: 12 }}>
            <Icon name="feather" size={28} stroke={1.3} /> 短文
          </h1>
          <p className="serif" style={{ fontSize: 16, color: 'var(--ink-2)', marginTop: 8, lineHeight: 1.55 }}>
            想到什麼寫什麼。沒有編輯、沒有 SEO、不負責任。共 142 則。
          </p>
        </div>

        {/* Year header */}
        <div style={{ display: 'flex', alignItems: 'center', gap: 12, marginBottom: 4, marginTop: 8 }}>
          <h3 className="serif" style={{ fontSize: 20, fontWeight: 500 }}>2026</h3>
          <div style={{ flex: 1, height: 1, background: 'var(--line)' }} />
          <span style={{ fontFamily: 'var(--font-mono)', fontSize: 11, color: 'var(--ink-3)' }}>23 則</span>
        </div>

        {/* Group by month */}
        <div style={{ fontFamily: 'var(--font-mono)', fontSize: 10.5, color: 'var(--ink-3)', textTransform: 'uppercase', letterSpacing: '0.08em', marginTop: 28, marginBottom: 4 }}>五月 · MAY</div>
        <div>
          {TWEETS.slice(0, 3).map(t => <TweetCard key={t.id} tweet={t} />)}
        </div>

        <div style={{ fontFamily: 'var(--font-mono)', fontSize: 10.5, color: 'var(--ink-3)', textTransform: 'uppercase', letterSpacing: '0.08em', marginTop: 28, marginBottom: 4 }}>四月 · APR</div>
        <div>
          {TWEETS.slice(3, 6).map(t => <TweetCard key={t.id} tweet={t} />)}
        </div>

        <div style={{ marginTop: 32, padding: 14, textAlign: 'center', fontFamily: 'var(--font-mono)', fontSize: 11, color: 'var(--ink-3)' }}>
          ── 載入更早的短文 ──
        </div>
      </div>
    </div>
  </div>
);

// === Search overlay ===========================================================
const SearchScreen = () => (
  <div className="jyu" data-screen-label="05 Search" style={{ background: 'rgba(0,0,0,0.3)' }}>
    <TopBar active="home" />
    {/* Modal overlay */}
    <div style={{ position: 'absolute', inset: 0, background: 'rgba(0,0,0,0.4)', backdropFilter: 'blur(4px)' }} />

    <div style={{
      position: 'absolute', top: 100, left: '50%', transform: 'translateX(-50%)',
      width: 'min(680px, 90%)',
      background: 'var(--card)', borderRadius: 14, boxShadow: 'var(--shadow-lg)',
      border: '1px solid var(--line)', overflow: 'hidden', zIndex: 10,
    }}>
      <div style={{ display: 'flex', alignItems: 'center', gap: 12, padding: '16px 18px', borderBottom: '1px solid var(--line)' }}>
        <Icon name="search" size={16} />
        <input
          defaultValue="設計系統"
          style={{ flex: 1, border: 0, outline: 'none', font: 'inherit', fontSize: 16, background: 'transparent', color: 'var(--ink)' }}
        />
        <kbd style={{ fontFamily: 'var(--font-mono)', fontSize: 10.5, padding: '2px 6px', background: 'var(--paper-2)', border: '1px solid var(--line)', borderRadius: 4, color: 'var(--ink-3)' }}>ESC</kbd>
      </div>

      {/* Filter chips */}
      <div style={{ padding: '10px 18px', display: 'flex', gap: 6, alignItems: 'center', borderBottom: '1px solid var(--line)', flexWrap: 'wrap' }}>
        <span style={{ fontFamily: 'var(--font-mono)', fontSize: 10.5, color: 'var(--ink-3)', marginRight: 4 }}>在</span>
        <button className="tag" style={{ background: 'var(--accent-soft)', color: 'var(--accent-ink)', borderColor: 'transparent' }}>全部</button>
        <button className="tag">Post</button>
        <button className="tag">Tweet</button>
        <span style={{ width: 1, height: 16, background: 'var(--line)', margin: '0 8px' }} />
        <span style={{ fontFamily: 'var(--font-mono)', fontSize: 10.5, color: 'var(--ink-3)', marginRight: 4 }}>Tag</span>
        <button className="tag solid">設計系統</button>
        <button className="tag">SaaS</button>
        <span style={{ flex: 1 }} />
        <button style={{ fontFamily: 'var(--font-mono)', fontSize: 10.5, color: 'var(--ink-3)' }}>清除</button>
      </div>

      {/* Results */}
      <div style={{ maxHeight: 380, overflowY: 'auto', padding: '8px 0' }}>
        <div style={{ padding: '4px 18px', fontFamily: 'var(--font-mono)', fontSize: 10, color: 'var(--ink-3)', textTransform: 'uppercase', letterSpacing: '0.08em' }}>文章 · 3 results</div>
        {[POSTS[0], POSTS[3]].map(p => (
          <div key={p.id} style={{ padding: '12px 18px', cursor: 'pointer', borderRadius: 6, display: 'flex', gap: 12, alignItems: 'flex-start' }}
            className="search-item">
            <div style={{ width: 44, height: 44, borderRadius: 6, overflow: 'hidden', flexShrink: 0 }}>
              <CoverArt kind={p.cover} hue={p.hue} />
            </div>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div className="serif" style={{ fontSize: 15, fontWeight: 500, marginBottom: 2 }}>
                在小型 SaaS 公司，我學到的三件關於<mark style={{ background: 'var(--accent-soft)', color: 'var(--accent-ink)', padding: '0 2px', borderRadius: 2 }}>設計系統</mark>的事
              </div>
              <div style={{ fontFamily: 'var(--font-mono)', fontSize: 10.5, color: 'var(--ink-3)' }}>設計筆記 · 2026-05-12</div>
            </div>
            <Icon name="chevronRight" size={14} />
          </div>
        ))}

        <div style={{ padding: '8px 18px 4px', fontFamily: 'var(--font-mono)', fontSize: 10, color: 'var(--ink-3)', textTransform: 'uppercase', letterSpacing: '0.08em' }}>短文 · 2 results</div>
        <div style={{ padding: '10px 18px', cursor: 'pointer' }} className="search-item">
          <div className="serif" style={{ fontSize: 14, color: 'var(--ink-2)' }}>
            …今天又把 <mark style={{ background: 'var(--accent-soft)', color: 'var(--accent-ink)', padding: '0 2px', borderRadius: 2 }}>設計系統</mark> 的 button token 改了一次，這次是因為一個工程師說「再不改我要哭了」。
          </div>
          <div style={{ fontFamily: 'var(--font-mono)', fontSize: 10.5, color: 'var(--ink-3)', marginTop: 4 }}>2026-03-22 · 16:08</div>
        </div>
      </div>

      <div style={{ padding: '8px 14px', borderTop: '1px solid var(--line)', display: 'flex', gap: 14, fontFamily: 'var(--font-mono)', fontSize: 10.5, color: 'var(--ink-3)' }}>
        <span><kbd style={{ padding: '1px 5px', background: 'var(--paper-2)', borderRadius: 3 }}>↑↓</kbd> 移動</span>
        <span><kbd style={{ padding: '1px 5px', background: 'var(--paper-2)', borderRadius: 3 }}>↵</kbd> 開啟</span>
        <span style={{ flex: 1 }} />
        <span>5 results in 12ms</span>
      </div>
    </div>
  </div>
);

Object.assign(window, { HomeScreen, PostListScreen, PostDetailScreen, TweetTimelineScreen, SearchScreen });
