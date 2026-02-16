{if $error}
    <div class="alert alert-warning">
        {$error}
    </div>
{else}
    <div class="panel panel-default card">
        <div class="panel-heading card-header">
            <h3 class="panel-title card-title">Remnawave — Connection &amp; Usage</h3>
        </div>
        <div class="panel-body card-body">
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-condensed">
                        <tr>
                            <td><strong>Subscription ID / User UUID</strong></td>
                            <td><code>{$sub_id}</code></td>
                        </tr>
                        <tr>
                            <td><strong>Status</strong></td>
                            <td>{if $status == 'Active'}<span class="text-success">Active</span>{elseif $status == 'Unlimited'}<span class="text-info">Unlimited</span>{else}<span class="text-danger">Inactive</span>{/if}</td>
                        </tr>
                        <tr>
                            <td><strong>Client / Email</strong></td>
                            <td>{$client_email}</td>
                        </tr>
                        <tr>
                            <td><strong>Downloaded</strong></td>
                            <td>{$traffic_used_gb} GB {if $traffic_total_gb > 0}/ {$traffic_total_gb} GB ({$traffic_percent}%){/if}</td>
                        </tr>
                        <tr>
                            <td><strong>Remained</strong></td>
                            <td>{if $remained_gb !== null}{$remained_gb} GB{else}&#8734;{/if}</td>
                        </tr>
                        <tr>
                            <td><strong>Last online</strong></td>
                            <td>{$last_online}</td>
                        </tr>
                        <tr>
                            <td><strong>Online now</strong></td>
                            <td>{if $online_now}<span class="text-success">Yes</span>{else}No{/if}</td>
                        </tr>
                        <tr>
                            <td><strong>Expiry</strong></td>
                            <td>{$expiry}</td>
                        </tr>
                    </table>
                </div>
            </div>

            {if $subscription_url}
                <hr>
                <h4>Subscription &amp; QR</h4>
                <p class="text-muted small">Copy the link or scan the QR code (click QR to copy link).</p>
                <div class="row">
                    <div class="col-sm-6 col-md-4">
                        <p><strong>Subscription URL</strong></p>
                        <button class="btn btn-default remnawave-copy" type="button" data-copy-value="{$subscription_url|escape:'html'}">Copy link</button>
                        <p class="small mt-2 remnawave-qr-wrap" style="cursor:pointer;display:inline-block" data-copy-value="{$subscription_url|escape:'html'}" title="Click to copy link">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&amp;data={$subscription_url|escape:'url'}" alt="Subscription QR" width="150" height="150">
                        </p>
                    </div>
                    {if $subscription_json_url}
                    <div class="col-sm-6 col-md-4">
                        <p><strong>Subscription JSON URL</strong></p>
                        <button class="btn btn-default remnawave-copy" type="button" data-copy-value="{$subscription_json_url|escape:'html'}">Copy link</button>
                        <p class="small mt-2 remnawave-qr-wrap" style="cursor:pointer;display:inline-block" data-copy-value="{$subscription_json_url|escape:'html'}" title="Click to copy link">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&amp;data={$subscription_json_url|escape:'url'}" alt="Subscription JSON QR" width="150" height="150">
                        </p>
                    </div>
                    {/if}
                </div>
                <div class="mt-3">
                    <p class="small text-muted"><strong>Quick add:</strong>
                        <a href="v2box://install-sub?url={$subscription_url|escape:'url'}" class="btn btn-sm btn-default">V2Box (Android)</a>
                        <a href="v2rayng://install-sub?url={$subscription_url|escape:'url'}" class="btn btn-sm btn-default">V2RayNG (Android)</a>
                        <a href="shadowrocket://add/sub?url={$subscription_url|escape:'url'}" class="btn btn-sm btn-default">Shadowrocket (iOS)</a>
                    </p>
                </div>
            {/if}

            {if $service_uris && count($service_uris) > 0}
                <hr>
                <h4>Connection link{if count($service_uris) > 1}s{/if}</h4>
                <p>Use in your app or import manually:</p>
                {foreach from=$service_uris item=uri name=uris}
                <div class="input-group" style="margin-bottom:8px">
                    <input type="text" class="form-control" id="remnawave_uri_{$smarty.foreach.uris.iteration}" value="{$uri}" readonly>
                    <span class="input-group-btn">
                        <button class="btn btn-default remnawave-copy" type="button" data-target="#remnawave_uri_{$smarty.foreach.uris.iteration}">Copy</button>
                    </span>
                </div>
                {/foreach}
            {/if}
        </div>
    </div>
    <script>
    (function(){
        function copyText(btn, text) {
            if (!text) return;
            function done() {
                if (btn.tagName === 'BUTTON') { var orig = btn.textContent; btn.textContent = 'Copied!'; setTimeout(function(){ btn.textContent = orig; }, 1500); }
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done);
            } else {
                var ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0'; document.body.appendChild(ta); ta.select();
                try { document.execCommand('copy'); done(); } catch(e) {}
                document.body.removeChild(ta);
            }
        }
        document.querySelectorAll('.remnawave-copy').forEach(function(btn){
            btn.addEventListener('click', function(){
                var text = this.getAttribute('data-copy-value');
                if (text) { copyText(this, text); return; }
                var id = this.getAttribute('data-target');
                var el = id ? document.querySelector(id) : null;
                if (el && el.value) { copyText(this, el.value); }
            });
        });
        document.querySelectorAll('.remnawave-qr-wrap').forEach(function(wrap){
            wrap.addEventListener('click', function(){
                var text = this.getAttribute('data-copy-value');
                if (text) copyText(this, text);
            });
        });
    })();
    </script>
{/if}
