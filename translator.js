/**
 * 翻訳の安定性向上のため、チャンク（100個のテキスト群）を順番に処理する関数
 * APIのレート制限による不安定さを避けるため、直列で実行します。
 */
async function processChunksSequentially(array, chunkSize, callback) {
    for (let i = 0; i < array.length; i += chunkSize) {
        const chunk = array.slice(i, i + chunkSize);
        // await を使用することで、前のチャンクの処理が完了するまで次のループに進まない
        await callback(chunk, i); 
    }
}

async function translatePage(targetLang) {
    if (targetLang === 'ja') {
        location.reload();
        return;
    }

    // 連続翻訳のための既存の翻訳済みフラグをすべて削除
    document.querySelectorAll('[data-translated="true"]').forEach(el => {
        el.removeAttribute('data-translated');
    });

    console.log("翻訳開始:", targetLang);

    // 翻訳対象とする要素を効率的に取得
	const rootSelector = ':not(#wpadminbar):not(.skip-translate):not(nav)'; 
// 修正後のセレクタ（a:not(.skip-translate)を追加、liからも除外）
const elements = document.querySelectorAll(`${rootSelector} p, ${rootSelector} h1, ${rootSelector} h2, ${rootSelector} h3, ${rootSelector} h4, ${rootSelector} h5, ${rootSelector} h6, ${rootSelector} li:not(.skip-translate), ${rootSelector} span:not([class*="icon"]), ${rootSelector} a:not(.skip-translate), ${rootSelector} td, ${rootSelector} th, ${rootSelector} button, ${rootSelector} label`);
    const texts = [];
    const nodes = [];
    
    // ⭐ キャッシュ用の変数
    const cacheKeyPrefix = 'translation_cache_';
    const cacheMap = {}; // 翻訳済みテキストを保持するマップ
    const textsToTranslate = []; // APIに送る必要があるテキストリスト
	
elements.forEach(el => {
    if (
        el.closest('#language-switcher') || 
        el.closest('#wpadminbar') || 
        el.closest('.skip-translate')
    ) {
        return;
    }

    // いま画面に出ている文字
    const currentText = el.textContent.trim();
    if (!currentText || currentText.length <= 1 || /^\d+$/.test(currentText)) return;

    // ★原文（日本語）を保持：初回だけ保存、以降はそれを使う
    const originalText = el.getAttribute('data-evrth-original') || currentText;
    if (!el.getAttribute('data-evrth-original')) {
        el.setAttribute('data-evrth-original', originalText);
    }

    // ★キャッシュキーは「原文 × targetLang」
    const cacheKey =
        cacheKeyPrefix +
        targetLang +
        '_' +
        btoa(encodeURIComponent(originalText)).replace(/=/g, '');

    const cachedText = localStorage.getItem(cacheKey);

    if (cachedText) {
        el.textContent = cachedText;
        el.setAttribute('data-translated', 'true');
    } else {
        // ★APIに送るのは原文
        textsToTranslate.push({ text: originalText, node: el, cacheKey: cacheKey });
    }
});

    console.log("API送信対象:", textsToTranslate.length, "個");
    
    if (textsToTranslate.length === 0) {
        console.log("翻訳対象なし (すべてキャッシュから読み込み)");
        return;
    }

    // APIに送るテキストリストを chunk に分割
    const chunkedTexts = [];
    const chunkedNodes = [];
    const chunkSize = 100;
    
    // APIに送るリストをtextsToTranslateから再構築
    for (let i = 0; i < textsToTranslate.length; i += chunkSize) {
        chunkedTexts.push(textsToTranslate.slice(i, i + chunkSize).map(t => t.text));
        chunkedNodes.push(textsToTranslate.slice(i, i + chunkSize).map(t => t.node));
    }


    try {
        await processChunksSequentially(chunkedTexts, 1, async (chunkOfTexts, index) => {
            const targetNodes = textsToTranslate.slice(index * chunkSize, (index + 1) * chunkSize);
            const chunk = chunkOfTexts[0]; // chunkOfTextsは配列の配列になっているため

            console.log(`チャンク処理開始: ${index * chunkSize + 1} - ${Math.min((index + 1) * chunkSize, textsToTranslate.length)}`);
            
            // WordPress AjaxハンドラーへのPOSTリクエスト
            const formData = new FormData();
            formData.append('action', 'evrth_translate'); 
            formData.append('texts', JSON.stringify(chunk)); 
            formData.append('targetLang', targetLang);
            formData.append('_wpnonce', evrthAjax.nonce); 

            const res = await fetch(evrthAjax.ajaxurl, {
                method: "POST",
                body: formData, 
            });

            const data = await res.json();
            
            if (data.success === false) {
                const errorMessage = data.data.message || "翻訳APIリクエスト中にエラーが発生しました。";
                console.error("APIエラー:", errorMessage);
                alert("翻訳エラー: " + errorMessage);
                throw new Error(errorMessage); 
            }
            
            // 翻訳結果の適用と ⭐ キャッシュへの保存
            const translated = data.data.map(t => t.translatedText);
            
            targetNodes.forEach((item, idx) => {
                const translatedText = translated[idx];
                item.node.textContent = translatedText;
                item.node.setAttribute('data-translated', 'true');
                
                // ⭐ ローカルストレージに保存
                localStorage.setItem(item.cacheKey, translatedText);
            });
        });

        console.log("翻訳完了");

    } catch (e) {
        console.error("全体的な処理エラーまたは中断:", e);
    }
}

document.addEventListener("DOMContentLoaded", () => {
    if (typeof evrthAjax === 'undefined' || typeof evrthAjax.ajaxurl === 'undefined') {
        console.error("WordPress Ajaxオブジェクト (evrthAjax) が見つかりません。PHP設定を確認してください。");
        return;
    }

    const SELECTOR = "#langSelect, .lang-select-menu";

    // change を確実に拾うため capture=true にする
    document.addEventListener("change", (e) => {
        const el = e.target;
        if (!el || !el.matches(SELECTOR)) return;

        const selectedLang = el.value;
        translatePage(selectedLang);

        // 通常/スティッキーを同期（片方だけ変えても表示が揃う）
        document.querySelectorAll(SELECTOR).forEach(s => {
            if (s !== el) s.value = selectedLang;
        });
    }, true);
});

window.translatePage = translatePage;
