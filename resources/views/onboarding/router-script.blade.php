<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>1-Command Router Setup — Wifikitaa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    @include('onboarding._styles')
    <style>
        .one-command-card {
            background: #040a17;
            border-radius: 12px;
            padding: 24px;
            margin: 20px 0;
            border: 1px solid #2561e8;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        .one-command-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .one-command-title {
            font-size: 14px;
            font-weight: 700;
            color: #2561e8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .copy-btn {
            background: #2561e8;
            color: #11111b;
            border: none;
            padding: 8px 18px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .copy-btn:hover { background: #e7eefc; transform: translateY(-1px); }
        .command-code-wrap {
            background: #11111b;
            border-radius: 8px;
            padding: 16px;
            border: 1px solid #45475a;
            overflow-x: auto;
        }
        .command-code {
            font-family: 'Consolas', 'Courier New', monospace;
            font-size: 13px;
            color: #a6e3a1;
            white-space: pre-wrap;
            word-break: break-all;
            line-height: 1.6;
        }

        /* RodLink Progress Breakdown Styling */
        .progress-box {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin: 24px 0;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
        }
        .progress-box-header {
            font-size: 15px;
            font-weight: 700;
            color: #040a17;
            margin-bottom: 4px;
        }
        .progress-box-sub {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 16px;
        }
        .progress-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .progress-step {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            padding: 14px 18px;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .progress-step.active {
            background: #eff6ff;
            border-color: #2561e8;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.08);
        }
        .progress-step.completed {
            background: #f0fdf4;
            border-color: #86efac;
        }
        .step-info {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .step-icon-wrap {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e2e8f0;
            color: #64748b;
            font-size: 13px;
            transition: all 0.3s ease;
        }
        .progress-step.active .step-icon-wrap {
            background: #2561e8;
            color: #ffffff;
        }
        .progress-step.completed .step-icon-wrap {
            background: #22c55e;
            color: #ffffff;
        }
        .step-text .title {
            font-size: 14px;
            font-weight: 600;
            color: #040a17;
        }
        .step-text .desc {
            font-size: 12px;
            color: #64748b;
        }
        .badge-status {
            font-size: 11px;
            font-weight: 700;
            text-transform: lowercase;
            padding: 4px 12px;
            border-radius: 20px;
            background: #f1f5f9;
            color: #64748b;
        }
        .progress-step.active .badge-status {
            background: #e7eefc;
            color: #040a17;
        }
        .progress-step.completed .badge-status {
            background: #dcfce7;
            color: #15803d;
        }
        .progress-step.failed {
            background: #fef2f2;
            border-color: #fecaca;
        }
        .progress-step.failed .step-icon-wrap {
            background: #dc2626;
            color: #ffffff;
        }
        .progress-step.failed .badge-status {
            background: #fee2e2;
            color: #b91c1c;
        }

        .success-banner {
            display: none;
            background: #10b981;
            color: white;
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            align-items: center;
            gap: 12px;
            animation: fadeIn 0.4s ease;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>
<div class="wizard-wrap">
    @include('onboarding._steps', ['current' => 1])

    <div class="card">
        <div class="card-header">
            <div class="step-icon"><i class="fa-solid fa-bolt"></i></div>
            <div>
                <h2>Connect Your MikroTik Router</h2>
                <p class="sub">Paste this one command into your router. It works on any RouterOS version and needs nothing opened on the router.</p>
            </div>
        </div>

        <div id="success-banner" class="success-banner">
            <i class="fa-solid fa-circle-check fa-2x"></i>
            <div>
                <strong style="font-size:16px;">Router connected!</strong>
                <p style="font-size:13px;margin:2px 0 0 0;opacity:0.9;">Your router is set up and has reported back to Wifikitaa.</p>
            </div>
        </div>

        <div class="one-command-card">
            <div class="one-command-header">
                <span class="one-command-title">
                    <i class="fa-solid fa-terminal"></i> WinBox Terminal Provision Command
                </span>
                <button class="copy-btn" onclick="copyCommand()">
                    <i class="fa-solid fa-copy"></i> Copy Command
                </button>
            </div>
            <div class="command-code-wrap">
                <div class="command-code" id="provision-command">{{ $command }}</div>
            </div>
        </div>

        <p style="font-size:13px;color:#64748b;margin-bottom:12px;">
            <i class="fa-solid fa-circle-info"></i> Open WinBox &rarr; <strong>New Terminal</strong> &rarr; Right Click &rarr; <strong>Paste</strong> &rarr; Hit Enter.
        </p>

        <div id="failure-note" class="alert alert-error" style="display:{{ $router->provision_status === 'failed' ? 'block' : 'none' }};margin-bottom:14px;padding:12px 14px;border-radius:8px;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;font-size:13px;">
            <strong>Setup finished with problems.</strong> <span id="failure-text">{{ $router->provision_status === 'failed' ? $router->provision_note : '' }}</span>
            The router builds its own hotspot when it has none, so this usually means it already has one in a
            layout the script did not expect. Paste the command again — if it still fails, ask support to look
            at the router's log (<code>/log print where topics~"script"</code>).
        </div>

        <!-- Progress Breakdown List -->
        <div class="progress-box">
            <div class="progress-box-header">Setup Progress</div>
            <div class="progress-box-sub">The router is being configured. This takes just a few seconds.</div>

            <div class="progress-list">
                <!-- Step 1 -->
                <div class="progress-step {{ $router->provision_status === 'pending' ? 'active' : 'completed' }}" id="step-1">
                    <div class="step-info">
                        <div class="step-icon-wrap" id="step-1-icon">
                            <i class="fa-solid {{ $router->provision_status === 'pending' ? 'fa-spinner fa-spin' : 'fa-check' }}"></i>
                        </div>
                        <div class="step-text">
                            <div class="title">Connecting to Router</div>
                            <div class="desc">Downloading the one-time bootstrap script.</div>
                        </div>
                    </div>
                    <span class="badge-status" id="step-1-badge">
                        {{ $router->provision_status === 'pending' ? 'waiting' : 'completed' }}
                    </span>
                </div>

                <!-- Step 2 -->
                <div class="progress-step {{ $router->provision_status === 'failed' ? 'failed' : ($router->provision_status === 'script_downloaded' ? 'active' : ($router->provision_status === 'completed' ? 'completed' : '')) }}" id="step-2">
                    <div class="step-info">
                        <div class="step-icon-wrap" id="step-2-icon">
                            <i class="fa-solid {{ $router->provision_status === 'failed' ? 'fa-triangle-exclamation' : ($router->provision_status === 'script_downloaded' ? 'fa-spinner fa-spin' : ($router->provision_status === 'completed' ? 'fa-check' : 'fa-floppy-disk')) }}"></i>
                        </div>
                        <div class="step-text">
                            <div class="title">Preparing the hotspot</div>
                            <div class="desc">Setting how customers log in after they pay.</div>
                        </div>
                    </div>
                    <span class="badge-status" id="step-2-badge">
                        {{ $router->provision_status === 'failed' ? 'failed' : ($router->provision_status === 'script_downloaded' ? 'in progress' : ($router->provision_status === 'completed' ? 'completed' : 'pending')) }}
                    </span>
                </div>

                <!-- Step 3 -->
                <div class="progress-step {{ $router->provision_status === 'completed' ? 'completed' : '' }}" id="step-3">
                    <div class="step-info">
                        <div class="step-icon-wrap" id="step-3-icon">
                            <i class="fa-solid {{ $router->provision_status === 'completed' ? 'fa-check' : 'fa-route' }}"></i>
                        </div>
                        <div class="step-text">
                            <div class="title">Hotspot &amp; walled garden</div>
                            <div class="desc">Letting customers reach your portal and the payment page before they pay.</div>
                        </div>
                    </div>
                    <span class="badge-status" id="step-3-badge">
                        {{ $router->provision_status === 'completed' ? 'completed' : 'pending' }}
                    </span>
                </div>

                <!-- Step 4 -->
                <div class="progress-step {{ $router->provision_status === 'completed' ? 'completed' : '' }}" id="step-4">
                    <div class="step-info">
                        <div class="step-icon-wrap" id="step-4-icon">
                            <i class="fa-solid {{ $router->provision_status === 'completed' ? 'fa-check' : 'fa-lock' }}"></i>
                        </div>
                        <div class="step-text">
                            <div class="title">Login page &amp; agent</div>
                            <div class="desc">Installing your branded login page and the small agent that reports to Wifikitaa.</div>
                        </div>
                    </div>
                    <span class="badge-status" id="step-4-badge">
                        {{ $router->provision_status === 'completed' ? 'completed' : 'pending' }}
                    </span>
                </div>

                <!-- Step 5 -->
                <div class="progress-step {{ $router->provision_status === 'completed' ? 'completed' : '' }}" id="step-5">
                    <div class="step-info">
                        <div class="step-icon-wrap" id="step-5-icon">
                            <i class="fa-solid {{ $router->provision_status === 'completed' ? 'fa-check' : 'fa-network-wired' }}"></i>
                        </div>
                        <div class="step-text">
                            <div class="title">Waiting for the router to report in</div>
                            <div class="desc">The router contacts Wifikitaa when setup is done.</div>
                        </div>
                    </div>
                    <span class="badge-status" id="step-5-badge">
                        {{ $router->provision_status === 'completed' ? 'completed' : 'pending' }}
                    </span>
                </div>
            </div>
        </div>

        <a href="{{ route('onboarding.packages') }}" id="btn-continue" class="btn-primary" style="display:block;text-align:center;text-decoration:none;margin-top:24px;">
            Continue to Step 2: Packages &rarr;
        </a>
    </div>
</div>

<script>
function copyCommand() {
    const text = document.getElementById('provision-command').innerText;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.querySelector('.copy-btn');
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
        setTimeout(() => {
            btn.innerHTML = '<i class="fa-solid fa-copy"></i> Copy Command';
        }, 2500);
    });
}

// Live Status Polling Loop
const token = "{{ $router->provision_token }}";
let isCompleted = "{{ $router->provision_status }}" === "completed";

if (isCompleted) {
    showCompletedUI();
} else {
    const pollInterval = setInterval(() => {
        fetch(`/provision/${token}/status`)
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateStatusUI(data.status);
                    const note = document.getElementById('failure-note');
                    if (data.status === 'failed') {
                        document.getElementById('failure-text').innerText = data.note || '';
                        note.style.display = 'block';
                    } else {
                        note.style.display = 'none';
                    }
                    if (data.status === 'completed') {
                        clearInterval(pollInterval);
                        showCompletedUI();
                    }
                }
            })
            .catch(err => console.log('Polling error:', err));
    }, 1500);
}

function updateStatusUI(status) {
    const step1 = document.getElementById('step-1');
    const step2 = document.getElementById('step-2');
    const step3 = document.getElementById('step-3');
    const step4 = document.getElementById('step-4');
    const step5 = document.getElementById('step-5');

    if (status === 'script_downloaded') {
        step1.className = 'progress-step completed';
        document.getElementById('step-1-icon').innerHTML = '<i class="fa-solid fa-check"></i>';
        document.getElementById('step-1-badge').innerText = 'completed';

        step2.className = 'progress-step active';
        document.getElementById('step-2-icon').innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        document.getElementById('step-2-badge').innerText = 'in progress';
    } else if (status === 'completed') {
        [step1, step2, step3, step4, step5].forEach((s, idx) => {
            s.className = 'progress-step completed';
            document.getElementById(`step-${idx+1}-icon`).innerHTML = '<i class="fa-solid fa-check"></i>';
            document.getElementById(`step-${idx+1}-badge`).innerText = 'completed';
        });
    } else if (status === 'failed') {
        // The router did run the script and reported back, so step 1 stands; step 2 is the one
        // that did not finish, and nothing past it ran. Left spinning, this looked identical to
        // a setup still in progress — the one thing a failure must never look like.
        step1.className = 'progress-step completed';
        document.getElementById('step-1-icon').innerHTML = '<i class="fa-solid fa-check"></i>';
        document.getElementById('step-1-badge').innerText = 'completed';

        step2.className = 'progress-step failed';
        document.getElementById('step-2-icon').innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
        document.getElementById('step-2-badge').innerText = 'failed';
    }
}

function showCompletedUI() {
    updateStatusUI('completed');
    const banner = document.getElementById('success-banner');
    if (banner) banner.style.display = 'flex';
}
</script>
</body>
</html>
