// Admin-facing screens for JYu's Blog

// === Admin: Post list ==========================================================
const AdminPostListScreen = () => {
  const rows = [
    { ...POSTS[0], status: 'published' },
    { ...POSTS[1], status: 'published' },
    { ...POSTS[2], status: 'draft', title: 'Bun + Vite vs. esbuild — 為什麼我們最後沒搬家' },
    { ...POSTS[3], status: 'published' },
    { ...POSTS[4], status: 'hidden' },
    { ...POSTS[5], status: 'draft', title: '一個關於 onboarding 的小實驗（草稿）' },
    { id: 99, title: '舊版設計系統的下台演出', status: 'deleted', tags: ['歸檔'], category: '設計筆記', date: '2025-12-04', views: 0, readTime: 5 },
  ];
  return (
    <div className="jyu admin" data-screen-label="06 Admin · Posts">
      <div style={{ display: 'flex', height: '100%' }}>
        <AdminSidebar active="posts" />
        <main style={{ flex: 1, padding: '24px 32px', overflow: 'auto' }}>
          {/* Page header */}
          <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', borderBottom: '1px solid var(--line)', paddingBottom: 16 }}>
            <div>
              <h1 className="serif" style={{ fontSize: 26, fontWeight: 500 }}>文章</h1>
              <div style={{ fontFamily: 'var(--font-mono)', fontSize: 11, color: 'var(--ink-3)', marginTop: 4 }}>
                共 68 篇 · 60 已發布 · 5 草稿 · 3 隱藏
              </div>
            </div>
            <div style={{ display: 'flex', gap: 8 }}>
              <button className="btn btn-ghost"><Icon name="filter" size={13} /> 篩選</button>
              <button className="btn btn-accent"><Icon name="plus" size={13} /> 新增文章</button>
            </div>
          </div>

          {/* Status tabs */}
          <div style={{ display: 'flex', gap: 4, marginTop: 16, padding: 4, background: 'var(--paper-2)', borderRadius: 8, width: 'fit-content' }}>
            {['全部 68', '已發布 60', '草稿 5', '隱藏 3', '回收桶 3'].map((t, i) => (
              <button key={t} style={{
                padding: '6px 12px', fontSize: 13, fontWeight: 500, borderRadius: 5,
                background: i === 0 ? 'var(--card)' : 'transparent',
                color: i === 0 ? 'var(--ink)' : 'var(--ink-2)',
                boxShadow: i === 0 ? 'var(--shadow-sm)' : 'none',
              }}>{t}</button>
            ))}
          </div>

          {/* Table */}
          <div style={{ marginTop: 16, background: 'var(--card)', borderRadius: 10, border: '1px solid var(--line)', overflow: 'hidden' }}>
            <div style={{
              display: 'grid', gridTemplateColumns: '1fr 110px 130px 90px 90px 80px',
              padding: '10px 18px', fontFamily: 'var(--font-mono)', fontSize: 10.5, color: 'var(--ink-3)',
              textTransform: 'uppercase', letterSpacing: '0.08em', borderBottom: '1px solid var(--line)',
              background: 'var(--paper-2)',
            }}>
              <div>標題</div>
              <div>狀態</div>
              <div>系列</div>
              <div style={{ textAlign: 'right' }}>觀看</div>
              <div style={{ textAlign: 'right' }}>更新</div>
              <div style={{ textAlign: 'right' }}>操作</div>
            </div>
            {rows.map(row => (
              <div key={row.id} style={{
                display: 'grid', gridTemplateColumns: '1fr 110px 130px 90px 90px 80px',
                padding: '14px 18px', borderBottom: '1px solid var(--line)', alignItems: 'center',
                fontSize: 13.5,
              }}>
                <div>
                  <div className="serif" style={{ fontSize: 15, fontWeight: 500, marginBottom: 3, color: row.status === 'deleted' ? 'var(--ink-3)' : 'var(--ink)', textDecoration: row.status === 'deleted' ? 'line-through' : 'none' }}>
                    {row.title}
                  </div>
                  <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
                    {row.tags.slice(0, 3).map(t => <Tag key={t}>{t}</Tag>)}
                  </div>
                </div>
                <div><Pill status={row.status} /></div>
                <div style={{ fontSize: 12.5, color: 'var(--ink-2)' }}>{row.category}</div>
                <div style={{ textAlign: 'right', fontFamily: 'var(--font-mono)', fontSize: 11.5, color: 'var(--ink-3)' }}>{row.views.toLocaleString()}</div>
                <div style={{ textAlign: 'right', fontFamily: 'var(--font-mono)', fontSize: 11.5, color: 'var(--ink-3)' }}>{row.date.slice(5)}</div>
                <div style={{ display: 'flex', gap: 2, justifyContent: 'flex-end' }}>
                  <button className="icon-btn" style={{ width: 26, height: 26 }}><Icon name="edit" size={13} /></button>
                  <button className="icon-btn" style={{ width: 26, height: 26 }}><Icon name="eyeOff" size={13} /></button>
                  <button className="icon-btn" style={{ width: 26, height: 26 }}><Icon name="dots" size={13} /></button>
                </div>
              </div>
            ))}
          </div>

          {/* Pagination */}
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: 14 }}>
            <span style={{ fontFamily: 'var(--font-mono)', fontSize: 11, color: 'var(--ink-3)' }}>顯示 1–7 / 共 68 篇</span>
            <div style={{ display: 'flex', gap: 4 }}>
              <button className="btn btn-ghost" style={{ height: 30, padding: '0 10px', fontSize: 12 }}>← 上一頁</button>
              <button className="btn btn-ghost" style={{ height: 30, padding: '0 10px', fontSize: 12 }}>下一頁 →</button>
            </div>
          </div>
        </main>
      </div>
    </div>
  );
};

// === Admin: Post editor ========================================================
const AdminEditorScreen = () => (
  <div className="jyu admin" data-screen-label="07 Admin · Editor">
    <div style={{ display: 'flex', height: '100%' }}>
      <AdminSidebar active="posts" />

      <main style={{ flex: 1, display: 'flex', flexDirection: 'column', minWidth: 0 }}>
        {/* Editor topbar */}
        <div style={{
          display: 'flex', alignItems: 'center', gap: 12, padding: '12px 24px',
          borderBottom: '1px solid var(--line)', background: 'var(--card)',
        }}>
          <button className="icon-btn" style={{ width: 28, height: 28, color: 'var(--ink-3)' }}><Icon name="chevronRight" size={14} /></button>
          <div style={{ fontFamily: 'var(--font-mono)', fontSize: 11, color: 'var(--ink-3)' }}>文章 / 編輯</div>
          <span style={{ fontFamily: 'var(--font-mono)', fontSize: 11, color: 'var(--ink-3)' }}>→</span>
          <span style={{ fontSize: 13, fontWeight: 500 }}>Tailwind v4 升級筆記</span>
          <Pill status="draft" />
          <div style={{ flex: 1 }} />
          <div style={{ fontFamily: 'var(--font-mono)', fontSize: 11, color: 'var(--ink-3)', display: 'flex', alignItems: 'center', gap: 5 }}>
            <span style={{ width: 6, height: 6, borderRadius: '50%', background: 'var(--good)' }} />
            已自動儲存 · 12 秒前
          </div>
          <button className="btn btn-ghost"><Icon name="eye" size={13} /> 預覽</button>
          <button className="btn btn-ghost"><Icon name="save" size={13} /> 存為草稿</button>
          <button className="btn btn-accent">發布</button>
        </div>

        <div style={{ flex: 1, display: 'grid', gridTemplateColumns: '1fr 300px', minHeight: 0 }}>
          {/* Editor pane */}
          <div style={{ overflow: 'auto', padding: '32px 56px 80px' }}>
            <div style={{ maxWidth: 720, margin: '0 auto' }}>
              {/* Title */}
              <input
                defaultValue="Tailwind v4 升級筆記：那些文件沒講清楚的事"
                style={{
                  width: '100%', border: 0, outline: 'none', background: 'transparent',
                  fontFamily: 'var(--font-serif)', fontSize: 36, fontWeight: 500,
                  letterSpacing: '-0.015em', color: 'var(--ink)', padding: 0, marginBottom: 8,
                }}
              />
              {/* Subtitle / excerpt */}
              <textarea
                defaultValue="我們把一個 8 萬行的 React 專案從 v3 升到 v4。整個過程花了一個週末，踩了五個地雷。本文是給未來自己的備忘錄。"
                rows={2}
                style={{
                  width: '100%', border: 0, outline: 'none', background: 'transparent',
                  fontFamily: 'var(--font-serif)', fontSize: 17, color: 'var(--ink-2)',
                  resize: 'none', padding: 0, marginBottom: 20, lineHeight: 1.55,
                }}
              />

              {/* Toolbar */}
              <div className="toolbar" style={{ marginBottom: 24, position: 'sticky', top: 0, zIndex: 2 }}>
                <button title="H1" style={{ fontFamily: 'var(--font-serif)', fontWeight: 500 }}>H1</button>
                <button title="H2" style={{ fontFamily: 'var(--font-serif)', fontWeight: 500 }}>H2</button>
                <button title="H3" style={{ fontFamily: 'var(--font-serif)', fontWeight: 500 }}>H3</button>
                <div className="sep" />
                <button title="Bold"><Icon name="bold" size={14} /></button>
                <button title="Italic"><Icon name="italic" size={14} /></button>
                <button title="Link"><Icon name="link" size={14} /></button>
                <button title="Code" className="active"><Icon name="code" size={14} /></button>
                <div className="sep" />
                <button title="Quote"><Icon name="quote" size={14} /></button>
                <button title="List"><Icon name="list" size={14} /></button>
                <button title="Image"><Icon name="image" size={14} /></button>
                <div className="sep" />
                <div style={{ display: 'flex', alignItems: 'center', gap: 4, padding: '0 4px' }}>
                  <button style={{ padding: '4px 8px', fontSize: 11.5, fontFamily: 'var(--font-mono)', borderRadius: 5, background: 'var(--ink)', color: 'var(--paper)' }}>WYSIWYG</button>
                  <button style={{ padding: '4px 8px', fontSize: 11.5, fontFamily: 'var(--font-mono)', borderRadius: 5, color: 'var(--ink-3)' }}>Markdown</button>
                </div>
              </div>

              {/* Body — mixed view to show editing */}
              <div className="serif" style={{ fontSize: 18, lineHeight: 1.7, color: 'var(--ink)' }}>
                <p style={{ marginBottom: 18 }}>
                  上週末我們把一個 8 萬行的 React 專案從 Tailwind v3 升到 v4。整個過程花了約 14 個小時，其中 11 個小時都在處理那些<em>文件沒寫</em>但會悄悄改變行為的東西。
                </p>
                <h2 style={{ fontSize: 26, fontWeight: 500, marginTop: 28, marginBottom: 12 }}>1. CSS-first config 不是可選的</h2>
                <p style={{ marginBottom: 16 }}>
                  v4 把 <code className="mono" style={{ background: 'var(--paper-2)', padding: '1px 6px', borderRadius: 4, fontSize: 15 }}>tailwind.config.js</code> 設為「相容模式」——它仍然能用，但有些新功能只在 CSS 設定生效…
                </p>
                {/* Active code block */}
                <div style={{ position: 'relative' }}>
                  <div className="code" style={{ boxShadow: '0 0 0 2px var(--accent), 0 0 0 5px var(--accent-soft)' }}>
                    <span className="c">{'/* v4 推薦寫法：直接寫在 CSS 裡 */'}</span>{'\n'}
                    <span className="k">@import</span> <span className="s">"tailwindcss"</span>;{'\n\n'}
                    <span className="k">@theme</span> {'{'}{'\n'}
                    {'  '}<span className="n">--color-accent</span>: <span className="s">oklch(0.55 0.18 25)</span>;{'\n'}
                    {'}'}
                  </div>
                  <div style={{
                    position: 'absolute', top: -12, left: 12, background: 'var(--accent)',
                    color: '#fff', fontFamily: 'var(--font-mono)', fontSize: 10, padding: '2px 8px', borderRadius: 4,
                  }}>CODE · CSS</div>
                </div>
                {/* Inline insert prompt */}
                <div style={{
                  marginTop: 16, padding: 10, border: '1px dashed var(--line-2)', borderRadius: 8,
                  display: 'flex', alignItems: 'center', gap: 8, color: 'var(--ink-3)', fontSize: 13,
                }}>
                  <Icon name="plus" size={14} />
                  <span>輸入「/」插入區塊（標題、圖片、引用、Callout、嵌入…）</span>
                </div>
              </div>
            </div>
          </div>

          {/* Right inspector */}
          <aside style={{ borderLeft: '1px solid var(--line)', background: 'var(--paper-2)', overflow: 'auto', padding: '20px 18px' }}>
            <div style={{ marginBottom: 18 }}>
              <label className="field-label">封面圖片</label>
              <div style={{ height: 120, borderRadius: 8, overflow: 'hidden', position: 'relative', cursor: 'pointer' }}>
                <CoverArt kind="wave" hue={160} />
                <button style={{
                  position: 'absolute', bottom: 8, right: 8, background: 'var(--ink)', color: 'var(--paper)',
                  fontSize: 11, padding: '4px 8px', borderRadius: 4, fontFamily: 'var(--font-mono)',
                }}>更換</button>
              </div>
            </div>

            <div style={{ marginBottom: 18 }}>
              <label className="field-label">摘要</label>
              <textarea rows={3} className="input" style={{ fontFamily: 'var(--font-serif)', fontSize: 13, resize: 'none' }}
                defaultValue="我們把一個 8 萬行的 React 專案從 v3 升到 v4。整個過程花了一個週末，踩了五個地雷。本文是給未來自己的備忘錄。" />
            </div>

            <div style={{ marginBottom: 18 }}>
              <label className="field-label">系列 Category（可多個）</label>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4 }}>
                <button className="tag solid" style={{ paddingRight: 4 }}>技術筆記 <Icon name="x" size={10} /></button>
                <button className="tag solid" style={{ paddingRight: 4 }}>工作流 <Icon name="x" size={10} /></button>
                <button className="tag" style={{ borderStyle: 'dashed' }}>+ 加入系列</button>
              </div>
            </div>

            <div style={{ marginBottom: 18 }}>
              <label className="field-label">Tag（可多個）</label>
              <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4 }}>
                {['Tailwind', 'React', '升級'].map(t => (
                  <button key={t} className="tag solid" style={{ paddingRight: 4 }}>{t} <Icon name="x" size={10} /></button>
                ))}
                <input placeholder="輸入新 tag…" style={{
                  fontFamily: 'var(--font-mono)', fontSize: 11, padding: '2px 8px',
                  background: 'var(--card)', border: '1px dashed var(--line-2)', borderRadius: 999, width: 100,
                  outline: 'none',
                }} />
              </div>
            </div>

            <div style={{ marginBottom: 18 }}>
              <label className="field-label">語言</label>
              <select className="input" style={{ fontSize: 13 }} defaultValue="zh">
                <option value="zh">繁體中文</option>
                <option value="en">English</option>
                <option value="ja">日本語</option>
                <option value="vi">Tiếng Việt</option>
                <option value="id">Bahasa Indonesia</option>
              </select>
            </div>

            <div style={{ marginBottom: 18 }}>
              <label className="field-label">發布狀態</label>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                {[
                  { v: 'draft', label: '草稿', desc: '不公開' },
                  { v: 'published', label: '已發布', desc: '前台顯示', active: true },
                  { v: 'hidden', label: '隱藏', desc: '保留但不顯示' },
                ].map(opt => (
                  <button key={opt.v} style={{
                    display: 'flex', alignItems: 'center', gap: 10, padding: 10, borderRadius: 6,
                    border: '1px solid ' + (opt.active ? 'var(--accent)' : 'var(--line)'),
                    background: opt.active ? 'var(--accent-soft)' : 'var(--card)',
                    textAlign: 'left',
                  }}>
                    <span style={{
                      width: 14, height: 14, borderRadius: '50%',
                      border: '1.5px solid ' + (opt.active ? 'var(--accent)' : 'var(--ink-3)'),
                      display: 'flex', alignItems: 'center', justifyContent: 'center',
                    }}>
                      {opt.active && <span style={{ width: 6, height: 6, borderRadius: '50%', background: 'var(--accent)' }} />}
                    </span>
                    <div>
                      <div style={{ fontSize: 12.5, fontWeight: 600 }}>{opt.label}</div>
                      <div style={{ fontSize: 11, color: 'var(--ink-3)' }}>{opt.desc}</div>
                    </div>
                  </button>
                ))}
              </div>
            </div>

            <div style={{ paddingTop: 16, borderTop: '1px solid var(--line)', fontFamily: 'var(--font-mono)', fontSize: 11, color: 'var(--ink-3)', lineHeight: 1.7 }}>
              <div>建立：2026-04-12 09:32</div>
              <div>最後編輯：剛剛</div>
              <div>字數：3,142</div>
              <div>閱讀時間：約 9 分鐘</div>
            </div>
          </aside>
        </div>
      </main>
    </div>
  </div>
);

// === Admin: Mobile Tweet Composer =============================================
const MobileTweetComposer = () => (
  <div className="jyu phone" data-screen-label="08 Admin · Mobile Tweet">
    <div className="phone-statusbar">
      <span>21:47</span>
      <span style={{ display: 'inline-flex', gap: 6, alignItems: 'center', fontSize: 11 }}>
        <span>●●●●</span><span>5G</span><span>●●●○</span>
      </span>
    </div>

    {/* Header */}
    <div style={{ display: 'flex', alignItems: 'center', padding: '6px 16px 12px', borderBottom: '1px solid var(--line)' }}>
      <button style={{ fontSize: 15, color: 'var(--ink-3)', fontWeight: 500 }}>取消</button>
      <div style={{ flex: 1, textAlign: 'center', fontSize: 15, fontWeight: 600, fontFamily: 'var(--font-serif)' }}>新短文</div>
      <button className="btn btn-accent" style={{ height: 30, padding: '0 14px', fontSize: 13 }}>發布</button>
    </div>

    {/* Body */}
    <div style={{ flex: 1, padding: '16px 18px', display: 'flex', flexDirection: 'column' }}>
      <div style={{ display: 'flex', gap: 12 }}>
        <div style={{
          width: 38, height: 38, borderRadius: '50%', background: 'var(--ink)',
          color: 'var(--paper)', display: 'flex', alignItems: 'center', justifyContent: 'center',
          fontFamily: 'var(--font-serif)', fontWeight: 600, fontSize: 17, flexShrink: 0,
        }}>J</div>
        <div style={{ flex: 1 }}>
          <div style={{ fontSize: 13, fontWeight: 600 }}>JYu</div>
          <div style={{ fontFamily: 'var(--font-mono)', fontSize: 10.5, color: 'var(--ink-3)' }}>@jyu · 現在</div>
        </div>
      </div>

      <textarea
        autoFocus
        rows={6}
        defaultValue={"半夜在咖啡店改 design system tokens。靈感最好的時候從來不是上班的時候，這件事讓我有點生氣。"}
        style={{
          width: '100%', border: 0, outline: 'none', background: 'transparent',
          marginTop: 14, fontFamily: 'var(--font-serif)', fontSize: 18,
          color: 'var(--ink)', resize: 'none', lineHeight: 1.55, padding: 0,
        }}
      />

      {/* Attached image */}
      <div style={{ position: 'relative', height: 160, borderRadius: 10, overflow: 'hidden', marginTop: 8 }}>
        <CoverArt kind="lines" hue={30} />
        <button style={{
          position: 'absolute', top: 8, right: 8, width: 26, height: 26, borderRadius: '50%',
          background: 'rgba(0,0,0,0.6)', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center',
        }}>
          <Icon name="x" size={14} />
        </button>
      </div>

      {/* Tags */}
      <div style={{ marginTop: 14 }}>
        <div className="field-label">Tag</div>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 5 }}>
          <span className="tag solid" style={{ paddingRight: 4 }}>雜記 <Icon name="x" size={10} /></span>
          <span className="tag solid" style={{ paddingRight: 4 }}>設計系統 <Icon name="x" size={10} /></span>
          <input placeholder="加 tag…" style={{
            fontFamily: 'var(--font-mono)', fontSize: 11, padding: '2px 8px',
            background: 'var(--paper-2)', border: '1px dashed var(--line-2)', borderRadius: 999, width: 90,
            outline: 'none',
          }} />
        </div>

        {/* Tag suggestions */}
        <div style={{ marginTop: 8, fontFamily: 'var(--font-mono)', fontSize: 10.5, color: 'var(--ink-3)' }}>建議</div>
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 5, marginTop: 4 }}>
          {['工作流', '咖啡店', '深夜', 'Design Tokens'].map(t => <span key={t} className="tag">+ {t}</span>)}
        </div>
      </div>

      <div style={{ flex: 1 }} />

      {/* Action toolbar */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 4, padding: '12px 0 8px', borderTop: '1px solid var(--line)' }}>
        <button className="icon-btn" style={{ width: 38, height: 38, color: 'var(--accent)' }}><Icon name="photo" size={18} /></button>
        <button className="icon-btn" style={{ width: 38, height: 38, color: 'var(--accent)' }}><Icon name="tag" size={18} /></button>
        <button className="icon-btn" style={{ width: 38, height: 38, color: 'var(--accent)' }}><Icon name="code" size={18} /></button>
        <button className="icon-btn" style={{ width: 38, height: 38, color: 'var(--accent)' }}><Icon name="link" size={18} /></button>
        <div style={{ flex: 1 }} />
        <div style={{ fontFamily: 'var(--font-mono)', fontSize: 11, color: 'var(--ink-3)' }}>
          63 / <span style={{ color: 'var(--ink) ' }}>500</span>
        </div>
      </div>
    </div>
  </div>
);

// === Admin: Tag & Category management =========================================
const AdminTagsScreen = () => {
  const tagsWithCounts = ALL_TAGS.slice(0, 18).map((t, i) => ({ name: t, count: 24 - i * 1 - (i % 3), posts: 12, tweets: 9 }));
  return (
    <div className="jyu admin" data-screen-label="09 Admin · Tags & Categories">
      <div style={{ display: 'flex', height: '100%' }}>
        <AdminSidebar active="tags" />
        <main style={{ flex: 1, padding: '24px 32px', overflow: 'auto' }}>
          <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', borderBottom: '1px solid var(--line)', paddingBottom: 16 }}>
            <div>
              <h1 className="serif" style={{ fontSize: 26, fontWeight: 500 }}>標籤與系列</h1>
              <div style={{ fontFamily: 'var(--font-mono)', fontSize: 11, color: 'var(--ink-3)', marginTop: 4 }}>
                {ALL_TAGS.length} 個 tag · {ALL_CATEGORIES.length} 個 category
              </div>
            </div>
          </div>

          <div style={{ display: 'grid', gridTemplateColumns: '1.5fr 1fr', gap: 32, marginTop: 24 }}>
            {/* Tags */}
            <section>
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 }}>
                <h2 className="serif" style={{ fontSize: 18, fontWeight: 500 }}>Tag</h2>
                <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                  <input placeholder="搜尋 tag…" className="input" style={{ height: 30, padding: '4px 10px', fontSize: 12, width: 160 }} />
                  <button className="btn btn-ghost" style={{ height: 30, fontSize: 12 }}><Icon name="plus" size={12} /> 新增</button>
                </div>
              </div>

              <div style={{ background: 'var(--card)', border: '1px solid var(--line)', borderRadius: 10, overflow: 'hidden' }}>
                {tagsWithCounts.map((t, i) => (
                  <div key={t.name} style={{
                    display: 'grid', gridTemplateColumns: '1fr 50px 50px 80px', alignItems: 'center',
                    padding: '10px 16px', borderBottom: i < tagsWithCounts.length - 1 ? '1px solid var(--line)' : 'none',
                    fontSize: 13,
                  }}>
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                      <span className="tag" style={{ background: 'var(--accent-soft)', color: 'var(--accent-ink)', borderColor: 'transparent' }}>#{t.name}</span>
                    </div>
                    <div style={{ fontFamily: 'var(--font-mono)', fontSize: 11, color: 'var(--ink-3)', textAlign: 'right' }}>{t.posts}P</div>
                    <div style={{ fontFamily: 'var(--font-mono)', fontSize: 11, color: 'var(--ink-3)', textAlign: 'right' }}>{t.tweets}T</div>
                    <div style={{ display: 'flex', gap: 2, justifyContent: 'flex-end' }}>
                      <button className="icon-btn" style={{ width: 26, height: 26 }}><Icon name="edit" size={12} /></button>
                      <button className="icon-btn" style={{ width: 26, height: 26 }}><Icon name="trash" size={12} /></button>
                    </div>
                  </div>
                ))}
              </div>
            </section>

            {/* Categories */}
            <section>
              <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 12 }}>
                <h2 className="serif" style={{ fontSize: 18, fontWeight: 500 }}>系列 Category</h2>
                <button className="btn btn-accent" style={{ height: 30, fontSize: 12 }}><Icon name="plus" size={12} /> 新增系列</button>
              </div>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                {ALL_CATEGORIES.map((c, i) => (
                  <div key={c.name} style={{
                    background: 'var(--card)', border: '1px solid var(--line)', borderRadius: 10,
                    padding: 16, display: 'flex', alignItems: 'center', gap: 14,
                  }}>
                    <div style={{
                      width: 44, height: 44, borderRadius: 8, overflow: 'hidden', flexShrink: 0,
                    }}>
                      <CoverArt kind={['grid', 'stack', 'wave', 'circles', 'dots'][i]} hue={14 + i * 50} />
                    </div>
                    <div style={{ flex: 1 }}>
                      <div className="serif" style={{ fontSize: 15, fontWeight: 500 }}>{c.name}</div>
                      <div style={{ fontFamily: 'var(--font-mono)', fontSize: 11, color: 'var(--ink-3)' }}>{c.count} 篇 · 排序：{i === 0 ? '手動' : '時間'}</div>
                    </div>
                    <button className="icon-btn"><Icon name="edit" size={14} /></button>
                    <button className="icon-btn"><Icon name="dots" size={14} /></button>
                  </div>
                ))}
                <button style={{
                  padding: 16, borderRadius: 10, border: '1.5px dashed var(--line-2)',
                  color: 'var(--ink-3)', fontSize: 13, display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6,
                }}>
                  <Icon name="plus" size={14} /> 新增一個系列
                </button>
              </div>
            </section>
          </div>
        </main>
      </div>
    </div>
  );
};

Object.assign(window, { AdminPostListScreen, AdminEditorScreen, MobileTweetComposer, AdminTagsScreen });
