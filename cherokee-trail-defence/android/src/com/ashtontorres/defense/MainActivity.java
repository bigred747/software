package com.ashtontorres.defense;

import android.app.Activity;
import android.content.Intent;
import android.graphics.Color;
import android.net.Uri;
import android.os.Bundle;
import android.webkit.ValueCallback;
import android.webkit.WebChromeClient;
import android.webkit.WebResourceError;
import android.webkit.WebResourceRequest;
import android.webkit.WebSettings;
import android.webkit.WebView;
import android.webkit.WebViewClient;
import java.io.InputStream;

public class MainActivity extends Activity {
    private static final int PICK_FILE = 41;
    private static final String SHEET = "file:///android_asset/Ashton-Torres.html";
    private WebView web;
    private ValueCallback<Uri[]> upload;
    private boolean failed;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        web = new WebView(this);
        web.setBackgroundColor(Color.parseColor("#10283f"));
        WebSettings settings = web.getSettings();
        settings.setJavaScriptEnabled(true);
        settings.setDomStorageEnabled(true);
        settings.setDatabaseEnabled(true);
        settings.setUseWideViewPort(true);
        settings.setLoadWithOverviewMode(true);
        settings.setSupportZoom(false);
        settings.setBuiltInZoomControls(false);
        settings.setDisplayZoomControls(false);
        settings.setAllowFileAccess(true);
        settings.setAllowContentAccess(true);
        settings.setMediaPlaybackRequiresUserGesture(false);
        web.setWebViewClient(new SheetClient());
        web.setWebChromeClient(new PhotoChooser());
        setContentView(web);
        try {
            InputStream in = getAssets().open("Ashton-Torres.html");
            in.close();
            web.loadUrl(SHEET);
        } catch (Exception err) {
            showMissing();
        }
    }

    private void showMissing() {
        if (failed || web == null) return;
        failed = true;
        web.loadData(
            "<p style=\"font:20px sans-serif;padding:24px;color:#f7fafc;background:#10283f\">The call sheet did not open. Install Ashton Torres again.</p>",
            "text/html",
            "utf-8"
        );
    }

    private final class SheetClient extends WebViewClient {
        @Override
        public void onReceivedError(WebView view, WebResourceRequest request, WebResourceError error) {
            if (request != null && request.isForMainFrame()) showMissing();
        }
    }

    @Override
    protected void onActivityResult(int requestCode, int resultCode, Intent data) {
        super.onActivityResult(requestCode, resultCode, data);
        if (requestCode != PICK_FILE || upload == null) return;
        Uri[] result = null;
        if (resultCode == RESULT_OK && data != null) {
            if (data.getData() != null) {
                result = new Uri[] { data.getData() };
            } else if (data.getClipData() != null && data.getClipData().getItemCount() > 0) {
                result = new Uri[] { data.getClipData().getItemAt(0).getUri() };
            }
        }
        upload.onReceiveValue(result);
        upload = null;
    }

    @Override
    public void onBackPressed() {
        if (web != null && web.canGoBack()) web.goBack();
        else super.onBackPressed();
    }

    private final class PhotoChooser extends WebChromeClient {
        @Override
        public boolean onShowFileChooser(WebView view, ValueCallback<Uri[]> callback, FileChooserParams params) {
            if (upload != null) upload.onReceiveValue(null);
            upload = callback;
            try {
                startActivityForResult(params.createIntent(), PICK_FILE);
            } catch (Exception err) {
                upload = null;
                callback.onReceiveValue(null);
                return false;
            }
            return true;
        }
    }

    @Override
    protected void onDestroy() {
        if (upload != null) {
            upload.onReceiveValue(null);
            upload = null;
        }
        super.onDestroy();
    }
}
