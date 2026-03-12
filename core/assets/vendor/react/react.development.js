function $(e, t) {
  for (var r = 0; r < t.length; r++) {
    const n = t[r];
    if (typeof n != "string" && !Array.isArray(n)) {
      for (const u in n)
        if (u !== "default" && !(u in e)) {
          const c = Object.getOwnPropertyDescriptor(n, u);
          c && Object.defineProperty(e, u, c.get ? c : {
            enumerable: !0,
            get: () => n[u]
          });
        }
    }
  }
  return Object.freeze(Object.defineProperty(e, Symbol.toStringTag, { value: "Module" }));
}
function A(e) {
  if (e.__esModule)
    return e;
  var t = e.default;
  if (typeof t == "function") {
    var r = function n() {
      return this instanceof n ? Reflect.construct(t, arguments, this.constructor) : t.apply(this, arguments);
    };
    r.prototype = t.prototype;
  } else
    r = {};
  return Object.defineProperty(r, "__esModule", { value: !0 }), Object.keys(e).forEach(function(n) {
    var u = Object.getOwnPropertyDescriptor(e, n);
    Object.defineProperty(r, n, u.get ? u : {
      enumerable: !0,
      get: function() {
        return e[n];
      }
    });
  }), r;
}
var w = { exports: {} }, o = {};
/**
 * @license React
 * react.production.min.js
 *
 * Copyright (c) Facebook, Inc. and its affiliates.
 *
 * This source code is licensed under the MIT license found in the
 * LICENSE file in the root directory of this source tree.
 */
var y = Symbol.for("react.element"), V = Symbol.for("react.portal"), L = Symbol.for("react.fragment"), M = Symbol.for("react.strict_mode"), N = Symbol.for("react.profiler"), F = Symbol.for("react.provider"), U = Symbol.for("react.context"), q = Symbol.for("react.forward_ref"), z = Symbol.for("react.suspense"), B = Symbol.for("react.memo"), H = Symbol.for("react.lazy"), R = Symbol.iterator;
function W(e) {
  return e === null || typeof e != "object" ? null : (e = R && e[R] || e["@@iterator"], typeof e == "function" ? e : null);
}
var k = { isMounted: function() {
    return !1;
  }, enqueueForceUpdate: function() {
  }, enqueueReplaceState: function() {
  }, enqueueSetState: function() {
  } }, j = Object.assign, P = {};
function p(e, t, r) {
  this.props = e, this.context = t, this.refs = P, this.updater = r || k;
}
p.prototype.isReactComponent = {};
p.prototype.setState = function(e, t) {
  if (typeof e != "object" && typeof e != "function" && e != null)
    throw Error("setState(...): takes an object of state variables to update or a function which returns an object of state variables.");
  this.updater.enqueueSetState(this, e, t, "setState");
};
p.prototype.forceUpdate = function(e) {
  this.updater.enqueueForceUpdate(this, e, "forceUpdate");
};
function x() {
}
x.prototype = p.prototype;
function h(e, t, r) {
  this.props = e, this.context = t, this.refs = P, this.updater = r || k;
}
var S = h.prototype = new x();
S.constructor = h;
j(S, p.prototype);
S.isPureReactComponent = !0;
var g = Array.isArray, I = Object.prototype.hasOwnProperty, E = { current: null }, T = { key: !0, ref: !0, __self: !0, __source: !0 };
function D(e, t, r) {
  var n, u = {}, c = null, a = null;
  if (t != null)
    for (n in t.ref !== void 0 && (a = t.ref), t.key !== void 0 && (c = "" + t.key), t)
      I.call(t, n) && !T.hasOwnProperty(n) && (u[n] = t[n]);
  var s = arguments.length - 2;
  if (s === 1)
    u.children = r;
  else if (1 < s) {
    for (var i = Array(s), l = 0; l < s; l++)
      i[l] = arguments[l + 2];
    u.children = i;
  }
  if (e && e.defaultProps)
    for (n in s = e.defaultProps, s)
      u[n] === void 0 && (u[n] = s[n]);
  return { $$typeof: y, type: e, key: c, ref: a, props: u, _owner: E.current };
}
function Y(e, t) {
  return { $$typeof: y, type: e.type, key: t, ref: e.ref, props: e.props, _owner: e._owner };
}
function b(e) {
  return typeof e == "object" && e !== null && e.$$typeof === y;
}
function G(e) {
  var t = { "=": "=0", ":": "=2" };
  return "$" + e.replace(/[=:]/g, function(r) {
    return t[r];
  });
}
var O = /\/+/g;
function m(e, t) {
  return typeof e == "object" && e !== null && e.key != null ? G("" + e.key) : t.toString(36);
}
function d(e, t, r, n, u) {
  var c = typeof e;
  (c === "undefined" || c === "boolean") && (e = null);
  var a = !1;
  if (e === null)
    a = !0;
  else
    switch (c) {
      case "string":
      case "number":
        a = !0;
        break;
      case "object":
        switch (e.$$typeof) {
          case y:
          case V:
            a = !0;
        }
    }
  if (a)
    return a = e, u = u(a), e = n === "" ? "." + m(a, 0) : n, g(u) ? (r = "", e != null && (r = e.replace(O, "$&/") + "/"), d(u, t, r, "", function(l) {
      return l;
    })) : u != null && (b(u) && (u = Y(u, r + (!u.key || a && a.key === u.key ? "" : ("" + u.key).replace(O, "$&/") + "/") + e)), t.push(u)), 1;
  if (a = 0, n = n === "" ? "." : n + ":", g(e))
    for (var s = 0; s < e.length; s++) {
      c = e[s];
      var i = n + m(c, s);
      a += d(c, t, r, i, u);
    }
  else if (i = W(e), typeof i == "function")
    for (e = i.call(e), s = 0; !(c = e.next()).done; )
      c = c.value, i = n + m(c, s++), a += d(c, t, r, i, u);
  else if (c === "object")
    throw t = String(e), Error("Objects are not valid as a React child (found: " + (t === "[object Object]" ? "object with keys {" + Object.keys(e).join(", ") + "}" : t) + "). If you meant to render a collection of children, use an array instead.");
  return a;
}
function v(e, t, r) {
  if (e == null)
    return e;
  var n = [], u = 0;
  return d(e, n, "", "", function(c) {
    return t.call(r, c, u++);
  }), n;
}
function J(e) {
  if (e._status === -1) {
    var t = e._result;
    t = t(), t.then(function(r) {
      (e._status === 0 || e._status === -1) && (e._status = 1, e._result = r);
    }, function(r) {
      (e._status === 0 || e._status === -1) && (e._status = 2, e._result = r);
    }), e._status === -1 && (e._status = 0, e._result = t);
  }
  if (e._status === 1)
    return e._result.default;
  throw e._result;
}
var f = { current: null }, _ = { transition: null }, K = { ReactCurrentDispatcher: f, ReactCurrentBatchConfig: _, ReactCurrentOwner: E }, Q = o.Children = { map: v, forEach: function(e, t, r) {
    v(e, function() {
      t.apply(this, arguments);
    }, r);
  }, count: function(e) {
    var t = 0;
    return v(e, function() {
      t++;
    }), t;
  }, toArray: function(e) {
    return v(e, function(t) {
      return t;
    }) || [];
  }, only: function(e) {
    if (!b(e))
      throw Error("React.Children.only expected to receive a single React element child.");
    return e;
  } }, X = o.Component = p, Z = o.Fragment = L, ee = o.Profiler = N, te = o.PureComponent = h, re = o.StrictMode = M, ne = o.Suspense = z, oe = o.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED = K, ue = o.cloneElement = function(e, t, r) {
  if (e == null)
    throw Error("React.cloneElement(...): The argument must be a React element, but you passed " + e + ".");
  var n = j({}, e.props), u = e.key, c = e.ref, a = e._owner;
  if (t != null) {
    if (t.ref !== void 0 && (c = t.ref, a = E.current), t.key !== void 0 && (u = "" + t.key), e.type && e.type.defaultProps)
      var s = e.type.defaultProps;
    for (i in t)
      I.call(t, i) && !T.hasOwnProperty(i) && (n[i] = t[i] === void 0 && s !== void 0 ? s[i] : t[i]);
  }
  var i = arguments.length - 2;
  if (i === 1)
    n.children = r;
  else if (1 < i) {
    s = Array(i);
    for (var l = 0; l < i; l++)
      s[l] = arguments[l + 2];
    n.children = s;
  }
  return { $$typeof: y, type: e.type, key: u, ref: c, props: n, _owner: a };
}, ce = o.createContext = function(e) {
  return e = { $$typeof: U, _currentValue: e, _currentValue2: e, _threadCount: 0, Provider: null, Consumer: null, _defaultValue: null, _globalName: null }, e.Provider = { $$typeof: F, _context: e }, e.Consumer = e;
}, ie = o.createElement = D, se = o.createFactory = function(e) {
  var t = D.bind(null, e);
  return t.type = e, t;
}, ae = o.createRef = function() {
  return { current: null };
}, fe = o.forwardRef = function(e) {
  return { $$typeof: q, render: e };
}, le = o.isValidElement = b, pe = o.lazy = function(e) {
  return { $$typeof: H, _payload: { _status: -1, _result: e }, _init: J };
}, ye = o.memo = function(e, t) {
  return { $$typeof: B, type: e, compare: t === void 0 ? null : t };
}, ve = o.startTransition = function(e) {
  var t = _.transition;
  _.transition = {};
  try {
    e();
  } finally {
    _.transition = t;
  }
}, de = o.unstable_act = function() {
  throw Error("act(...) is not supported in production builds of React.");
}, _e = o.useCallback = function(e, t) {
  return f.current.useCallback(e, t);
}, me = o.useContext = function(e) {
  return f.current.useContext(e);
}, he = o.useDebugValue = function() {
}, Se = o.useDeferredValue = function(e) {
  return f.current.useDeferredValue(e);
}, Ee = o.useEffect = function(e, t) {
  return f.current.useEffect(e, t);
}, be = o.useId = function() {
  return f.current.useId();
}, Re = o.useImperativeHandle = function(e, t, r) {
  return f.current.useImperativeHandle(e, t, r);
}, ge = o.useInsertionEffect = function(e, t) {
  return f.current.useInsertionEffect(e, t);
}, Oe = o.useLayoutEffect = function(e, t) {
  return f.current.useLayoutEffect(e, t);
}, Ce = o.useMemo = function(e, t) {
  return f.current.useMemo(e, t);
}, $e = o.useReducer = function(e, t, r) {
  return f.current.useReducer(e, t, r);
}, we = o.useRef = function(e) {
  return f.current.useRef(e);
}, ke = o.useState = function(e) {
  return f.current.useState(e);
}, je = o.useSyncExternalStore = function(e, t, r) {
  return f.current.useSyncExternalStore(e, t, r);
}, Pe = o.useTransition = function() {
  return f.current.useTransition();
}, xe = o.version = "18.2.0";
const Ie = /* @__PURE__ */ $({
  __proto__: null,
  Children: Q,
  Component: X,
  Fragment: Z,
  Profiler: ee,
  PureComponent: te,
  StrictMode: re,
  Suspense: ne,
  __SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED: oe,
  cloneElement: ue,
  createContext: ce,
  createElement: ie,
  createFactory: se,
  createRef: ae,
  default: o,
  forwardRef: fe,
  isValidElement: le,
  lazy: pe,
  memo: ye,
  startTransition: ve,
  unstable_act: de,
  useCallback: _e,
  useContext: me,
  useDebugValue: he,
  useDeferredValue: Se,
  useEffect: Ee,
  useId: be,
  useImperativeHandle: Re,
  useInsertionEffect: ge,
  useLayoutEffect: Oe,
  useMemo: Ce,
  useReducer: $e,
  useRef: we,
  useState: ke,
  useSyncExternalStore: je,
  useTransition: Pe,
  version: xe
}, [o]), Te = /* @__PURE__ */ A(Ie);
w.exports = Te;
var C = w.exports;
const De = /* @__PURE__ */ $({
  __proto__: null,
  default: C
}, [C]);
export {
  C as a,
  A as g,
  De as r
};
