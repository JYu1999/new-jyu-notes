// Main app — composes all screens onto the design canvas

const { useState, useEffect } = React;

const TWEAK_DEFAULTS = /*EDITMODE-BEGIN*/{
  "theme": "light",
  "accent": "terracotta",
  "density": "comfortable",
  "serif": "newsreader"
}/*EDITMODE-END*/;

function applyTokens(t) {
  const root = document.documentElement;
  root.setAttribute('data-theme', t.theme);
  root.setAttribute('data-accent', t.accent);
  root.setAttribute('data-density', t.density);
  // serif swap
  const serifMap = {
    newsreader: "'Newsreader', 'Source Serif 4', Georgia, serif",
    fraunces: "'Fraunces', 'Source Serif 4', Georgia, serif",
    eb: "'EB Garamond', Georgia, serif",
  };
  root.style.setProperty('--font-serif', serifMap[t.serif] || serifMap.newsreader);
}

function TweaksUI() {
  const [t, setTweak] = useTweaks(TWEAK_DEFAULTS);
  useEffect(() => { applyTokens(t); }, [t]);

  const swatch = { terracotta: '#b2543b', forest: '#3f6b4e', indigo: '#4a557d', ink: '#2a221c' };
  return (
    <TweaksPanel title="Tweaks">
      <TweakSection label="主題">
        <TweakRadio label="模式" value={t.theme} onChange={v => setTweak('theme', v)}
          options={[{ value: 'light', label: '亮色' }, { value: 'dark', label: '暗黑' }]} />
      </TweakSection>
      <TweakSection label="強調色">
        <TweakRow label="顏色">
          <div style={{ display: 'flex', gap: 6 }}>
            {Object.entries(swatch).map(([k, c]) => (
              <button key={k} onClick={() => setTweak('accent', k)} title={k}
                style={{
                  width: 24, height: 24, borderRadius: '50%', background: c,
                  border: t.accent === k ? '2px solid #fff' : '2px solid transparent',
                  boxShadow: t.accent === k ? '0 0 0 2px ' + c : '0 0 0 1px rgba(0,0,0,0.1)',
                  cursor: 'pointer', padding: 0,
                }} />
            ))}
          </div>
        </TweakRow>
      </TweakSection>
      <TweakSection label="密度">
        <TweakRadio label="間距" value={t.density} onChange={v => setTweak('density', v)}
          options={[{ value: 'comfortable', label: '寬鬆' }, { value: 'compact', label: '緊湊' }]} />
      </TweakSection>
      <TweakSection label="字體">
        <TweakSelect label="襯線" value={t.serif} onChange={v => setTweak('serif', v)}
          options={[
            { value: 'newsreader', label: 'Newsreader' },
            { value: 'fraunces', label: 'Fraunces' },
            { value: 'eb', label: 'EB Garamond' },
          ]} />
      </TweakSection>
    </TweaksPanel>
  );
}

// === Canvas ===================================================================
function App() {
  // Apply tweaks on initial paint too (TweaksUI is only mounted when edit-mode activates)
  useEffect(() => { applyTokens(TWEAK_DEFAULTS); }, []);

  return (
    <DesignCanvas>
      <TweaksUI />

      <DCSection id="intro" title="JYu's Blog" subtitle="高仿真設計 · 公開站 + 後台 · 9 個畫面 · 拖曳重排，雙擊放大">
        <DCArtboard id="home" label="01 · 首頁 / 混合動態" width={1280} height={860}>
          <HomeScreen />
        </DCArtboard>

        <DCArtboard id="list" label="02 · 文章列表" width={1280} height={860}>
          <PostListScreen />
        </DCArtboard>
      </DCSection>

      <DCSection id="reading" title="閱讀與探索" subtitle="長文閱讀體驗、短文時間軸與全站搜尋">
        <DCArtboard id="post" label="03 · 單篇文章" width={920} height={1100}>
          <PostDetailScreen />
        </DCArtboard>

        <DCArtboard id="tweets" label="04 · 短文時間軸" width={900} height={960}>
          <TweetTimelineScreen />
        </DCArtboard>

        <DCArtboard id="search" label="05 · 搜尋 (⌘K)" width={1100} height={720}>
          <SearchScreen />
        </DCArtboard>
      </DCSection>

      <DCSection id="mobile" title="公開站 · 手機版" subtitle="首頁、單篇文章、短文時間軸 — 響應式設計">
        <DCArtboard id="m-home" label="M01 · 手機首頁" width={390} height={780}>
          <MobileHomeScreen />
        </DCArtboard>

        <DCArtboard id="m-post" label="M02 · 閱讀模式" width={390} height={780}>
          <MobilePostDetailScreen />
        </DCArtboard>

        <DCArtboard id="m-tweets" label="M03 · 短文時間軸" width={390} height={780}>
          <MobileTweetTimelineScreen />
        </DCArtboard>
      </DCSection>

      <DCSection id="admin" title="後台" subtitle="桌機管理與行動裝置發文">
        <DCArtboard id="admin-list" label="06 · 文章管理" width={1280} height={860}>
          <AdminPostListScreen />
        </DCArtboard>

        <DCArtboard id="admin-editor" label="07 · 文章編輯器" width={1440} height={920}>
          <AdminEditorScreen />
        </DCArtboard>

        <DCArtboard id="mobile-tweet" label="08 · 手機發短文" width={390} height={760}>
          <MobileTweetComposer />
        </DCArtboard>

        <DCArtboard id="admin-tags" label="09 · Tag / Category 管理" width={1280} height={860}>
          <AdminTagsScreen />
        </DCArtboard>
      </DCSection>

      <DCPostIt x={40} y={40} rotate={-2}>
        繁中為主、輕量編輯系統，外觀偏編輯風格（不像 dashboard）。試試右下角 Tweaks 切主題與強調色。
      </DCPostIt>
    </DesignCanvas>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
