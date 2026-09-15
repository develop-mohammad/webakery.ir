/**
 * نمودارهای سبک برای سئو استودیو — بدون وابستگی خارجی.
 */
(function (global) {
	'use strict';

	function css(name, fallback) {
		var v = getComputedStyle(document.documentElement).getPropertyValue(name);
		return (v && v.trim()) || fallback;
	}

	function size(canvas) {
		var dpr = window.devicePixelRatio || 1;
		var w = canvas.clientWidth || 320;
		var h = canvas.clientHeight || 200;
		canvas.width = Math.round(w * dpr);
		canvas.height = Math.round(h * dpr);
		var ctx = canvas.getContext('2d');
		ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
		return { ctx: ctx, w: w, h: h };
	}

	function niceMax(n) {
		if (n <= 5) return 5;
		if (n <= 10) return 10;
		if (n <= 20) return 20;
		if (n <= 50) return 50;
		return Math.ceil(n / 10) * 10;
	}

	function line(canvas, labels, values, opts) {
		opts = opts || {};
		var s = size(canvas);
		var ctx = s.ctx;
		var pad = { t: 16, r: 12, b: 28, l: 36 };
		var w = s.w - pad.l - pad.r;
		var h = s.h - pad.t - pad.b;
		var invert = !!opts.invert;
		var max = opts.max || niceMax(Math.max.apply(null, values.concat([1])));
		var min = opts.min || 0;
		var range = Math.max(1, max - min);
		var accent = opts.color || css('--wbss-accent', '#1565c0');
		var grid = css('--wbss-grid', '#e8eaed');
		var ink = css('--wbss-muted', '#5f6368');

		ctx.clearRect(0, 0, s.w, s.h);
		ctx.strokeStyle = grid;
		ctx.lineWidth = 1;
		for (var g = 0; g <= 4; g++) {
			var gy = pad.t + (h * g) / 4;
			ctx.beginPath();
			ctx.moveTo(pad.l, gy);
			ctx.lineTo(pad.l + w, gy);
			ctx.stroke();
			var tick = invert ? (min + (range * g) / 4) : (max - (range * g) / 4);
			ctx.fillStyle = ink;
			ctx.font = '11px Tahoma, sans-serif';
			ctx.textAlign = 'left';
			ctx.fillText(String(Math.round(tick)), 4, gy + 4);
		}

		if (!values.length) return;

		function xy(i, v) {
			var x = pad.l + (values.length === 1 ? w / 2 : (w * i) / (values.length - 1));
			var n = (v - min) / range;
			var y = invert ? pad.t + n * h : pad.t + (1 - n) * h;
			return { x: x, y: y };
		}

		ctx.beginPath();
		ctx.strokeStyle = accent;
		ctx.lineWidth = 2.2;
		values.forEach(function (v, i) {
			var p = xy(i, v);
			if (i === 0) ctx.moveTo(p.x, p.y);
			else ctx.lineTo(p.x, p.y);
		});
		ctx.stroke();

		var last = xy(values.length - 1, values[values.length - 1]);
		ctx.lineTo(pad.l + w, pad.t + h);
		ctx.lineTo(pad.l, pad.t + h);
		ctx.closePath();
		ctx.fillStyle = accent;
		ctx.globalAlpha = 0.08;
		ctx.fill();
		ctx.globalAlpha = 1;

		values.forEach(function (v, i) {
			var p = xy(i, v);
			ctx.beginPath();
			ctx.fillStyle = '#fff';
			ctx.arc(p.x, p.y, 3.2, 0, Math.PI * 2);
			ctx.fill();
			ctx.strokeStyle = accent;
			ctx.lineWidth = 1.6;
			ctx.stroke();
		});

		ctx.fillStyle = ink;
		ctx.font = '11px Tahoma, sans-serif';
		ctx.textAlign = 'center';
		var step = values.length > 8 ? Math.ceil(values.length / 6) : 1;
		labels.forEach(function (lb, i) {
			if (i % step !== 0 && i !== labels.length - 1) return;
			var p = xy(i, values[i]);
			ctx.fillText(lb, p.x, s.h - 8);
		});

		void last;
	}

	function bar(canvas, labels, values, opts) {
		opts = opts || {};
		var s = size(canvas);
		var ctx = s.ctx;
		var pad = { t: 12, r: 8, b: 36, l: 28 };
		var w = s.w - pad.l - pad.r;
		var h = s.h - pad.t - pad.b;
		var max = niceMax(Math.max.apply(null, values.concat([1])));
		var accent = opts.color || css('--wbss-accent', '#1565c0');
		var grid = css('--wbss-grid', '#e8eaed');
		var ink = css('--wbss-muted', '#5f6368');
		ctx.clearRect(0, 0, s.w, s.h);
		ctx.strokeStyle = grid;
		for (var g = 0; g <= 3; g++) {
			var gy = pad.t + (h * g) / 3;
			ctx.beginPath();
			ctx.moveTo(pad.l, gy);
			ctx.lineTo(pad.l + w, gy);
			ctx.stroke();
		}
		var n = values.length || 1;
		var gap = 10;
		var bw = Math.max(10, (w - gap * n) / n);
		values.forEach(function (v, i) {
			var bh = (v / max) * h;
			var x = pad.l + i * (bw + gap) + gap / 2;
			var y = pad.t + h - bh;
			ctx.fillStyle = accent;
			ctx.fillRect(x, y, bw, bh);
			ctx.fillStyle = ink;
			ctx.font = '11px Tahoma, sans-serif';
			ctx.textAlign = 'center';
			ctx.fillText(labels[i] || '', x + bw / 2, s.h - 10);
			if (v) ctx.fillText(String(v), x + bw / 2, y - 4);
		});
	}

	function donut(canvas, labels, values, colors) {
		var s = size(canvas);
		var ctx = s.ctx;
		var total = values.reduce(function (a, b) { return a + b; }, 0) || 1;
		var cx = s.w * 0.34;
		var cy = s.h / 2;
		var r = Math.min(s.w, s.h) * 0.32;
		var start = -Math.PI / 2;
		ctx.clearRect(0, 0, s.w, s.h);
		values.forEach(function (v, i) {
			var slice = (v / total) * Math.PI * 2;
			ctx.beginPath();
			ctx.moveTo(cx, cy);
			ctx.fillStyle = colors[i % colors.length];
			ctx.arc(cx, cy, r, start, start + slice);
			ctx.closePath();
			ctx.fill();
			start += slice;
		});
		ctx.beginPath();
		ctx.fillStyle = css('--wbss-card', '#fff');
		ctx.arc(cx, cy, r * 0.58, 0, Math.PI * 2);
		ctx.fill();
		ctx.fillStyle = css('--wbss-ink', '#202124');
		ctx.font = '700 18px Tahoma, sans-serif';
		ctx.textAlign = 'center';
		ctx.fillText(String(values.reduce(function (a, b) { return a + b; }, 0)), cx, cy + 6);

		var lx = s.w * 0.62;
		var ly = 18;
		ctx.textAlign = 'right';
		ctx.font = '12px Tahoma, sans-serif';
		labels.forEach(function (lb, i) {
			ctx.fillStyle = colors[i % colors.length];
			ctx.fillRect(s.w - 14, ly + i * 22, 10, 10);
			ctx.fillStyle = css('--wbss-ink', '#202124');
			ctx.fillText(lb + ' (' + values[i] + ')', s.w - 22, ly + 10 + i * 22);
		});
		void lx;
	}

	global.WbssCharts = { line: line, bar: bar, donut: donut };
})(window);
