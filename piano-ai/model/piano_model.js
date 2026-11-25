(function (root, factory) {
  "use strict";
  var model = factory();
  if (typeof module === "object" && module.exports) {
    module.exports = model;
  } else {
    root.PianoModel = model;
  }
}(typeof self !== "undefined" ? self : this, function () {
  "use strict";

  var NOTE_COUNT = 88;
  var FIRST_MIDI_NOTE = 21;
  var CONTEXT_LENGTH = 4;
  var FEATURE_COUNT = NOTE_COUNT * CONTEXT_LENGTH;
  var STORAGE_KEY = "piano-88-model-v2";
  var LEARNING_RATE = 0.18;
  var MAX_WEIGHT = 100000;

  function create() {
    return {
      version: 3,
      weights: Array.from({ length: NOTE_COUNT }, function () {
        return Array(FEATURE_COUNT + 1).fill(0);
      }),
      examples: 0,
      history: []
    };
  }

  function isValid(model) {
    return model && (model.version === 2 || model.version === 3) &&
      Array.isArray(model.weights) && model.weights.length === NOTE_COUNT &&
      model.weights.every(function (row) {
        return Array.isArray(row) && row.length === FEATURE_COUNT + 1 &&
          row.every(function (weight) {
            return Number.isFinite(weight) &&
              Math.abs(weight) <= MAX_WEIGHT;
          });
      }) &&
      Array.isArray(model.history) && model.history.length <= 10000 &&
      model.history.every(function (note) {
        return Number.isInteger(note) && note >= 0 && note < NOTE_COUNT;
      }) &&
      Number.isSafeInteger(model.examples) && model.examples >= 0 &&
      (model.version !== 2 || model.examples >= model.history.length);
  }

  function load() {
    try {
      var saved = window.localStorage.getItem(STORAGE_KEY);
      if (!saved) return { model: create(), warning: false };

      var model = JSON.parse(saved);
      if (isValid(model)) {
        model.version = 3;
        return { model: model, warning: false };
      }
    } catch (error) {
      return { model: create(), warning: true };
    }

    return { model: create(), warning: true };
  }

  function save(model) {
    if (!isValid(model)) return false;
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(model));
      return true;
    } catch (error) {
      return false;
    }
  }

  function activeFeatures(history) {
    var context = (Array.isArray(history) ? history : [])
      .slice(-CONTEXT_LENGTH)
      .filter(function (note) {
        return Number.isInteger(note) && note >= 0 && note < NOTE_COUNT;
      });
    var offset = CONTEXT_LENGTH - context.length;
    var active = context.map(function (note, index) {
      return (offset + index) * NOTE_COUNT + note;
    });
    active.push(FEATURE_COUNT);
    return active;
  }

  function probabilities(model, history) {
    if (!model || !Array.isArray(model.weights) ||
        model.weights.length !== NOTE_COUNT) {
      return Array(NOTE_COUNT).fill(0);
    }
    var active = activeFeatures(history);
    var logits = model.weights.map(function (row) {
      if (!Array.isArray(row) || row.length !== FEATURE_COUNT + 1) {
        return 0;
      }
      return active.reduce(function (sum, index) {
        var weight = row[index];
        return Number.isFinite(weight) ? sum + weight : sum;
      }, 0);
    });
    return logits.map(function (logit) {
      if (logit >= 0) return 1 / (1 + Math.exp(-logit));
      var exp = Math.exp(logit);
      return exp / (1 + exp);
    });
  }

  function predict(model, history, sharedModel) {
    var personal = probabilities(model, history);
    if (!sharedModel || !sharedModel.examples) return personal;

    var shared = probabilities(sharedModel, history);
    return personal.map(function (score, note) {
      return score * 0.35 + shared[note] * 0.65;
    });
  }

  function learnFromContext(model, history, targetNotes) {
    if (!isValid(model)) return false;
    var active = activeFeatures(history);
    var scores = probabilities(model, history);
    var rawTargets = Array.isArray(targetNotes) ? targetNotes : [targetNotes];
    var targets = rawTargets.filter(function (note, index) {
      return Number.isInteger(note) && note >= 0 && note < NOTE_COUNT &&
        rawTargets.indexOf(note) === index;
    });
    if (!targets.length) return false;

    model.weights.forEach(function (row, note) {
      var error = scores[note] - (targets.indexOf(note) !== -1 ? 1 : 0);
      active.forEach(function (feature) {
        var nextWeight = row[feature] - LEARNING_RATE * error;
        row[feature] = Math.max(-MAX_WEIGHT, Math.min(MAX_WEIGHT,
          Number.isFinite(nextWeight) ? nextWeight : 0));
      });
    });
    model.examples++;
    return true;
  }

  function learn(model, note) {
    if (!model || !Array.isArray(model.history)) return false;
    return learnChord(model, model.history, [note]);
  }

  function learnChord(model, history, notes) {
    if (!model || !Array.isArray(model.history) || !Array.isArray(notes)) return false;
    var validNotes = notes.filter(function (note) {
      return Number.isInteger(note) && note >= 0 && note < NOTE_COUNT;
    });
    validNotes = validNotes.filter(function (note, index) {
      return validNotes.indexOf(note) === index;
    });
    if (!validNotes.length) return false;
    if (!learnFromContext(model, history, validNotes)) return false;
    Array.prototype.push.apply(model.history, validNotes);
    if (model.history.length > 10000) {
      model.history.splice(0, model.history.length - 10000);
    }
    return true;
  }

  function sample(scores) {
    var total = 0;
    var lastPositive = -1;
    for (var note = 0; note < scores.length; note++) {
      if (Number.isFinite(scores[note]) && scores[note] > 0) {
        total += scores[note];
        lastPositive = note;
      }
    }
    if (lastPositive === -1) {
      throw new Error("Cannot sample from scores without a positive value.");
    }

    var choice = Math.random() * total;
    for (var index = 0; index < scores.length; index++) {
      if (Number.isFinite(scores[index]) && scores[index] > 0) {
        choice -= scores[index];
        if (choice < 0) return index;
      }
    }
    return lastPositive;
  }

  return {
    create: create,
    isValid: isValid,
    load: load,
    save: save,
    learn: learn,
    learnChord: learnChord,
    learnFromContext: learnFromContext,
    predict: predict,
    sample: sample,
    firstMidiNote: FIRST_MIDI_NOTE,
    noteCount: NOTE_COUNT
  };
}));
