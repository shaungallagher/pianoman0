(function () {
  "use strict";

  var FIRST_MIDI_NOTE = 21;
  var LAST_MIDI_NOTE = 108;
  var VISIBLE_KEY_COUNT = 25;
  var MIN_VISIBLE_MIDI = FIRST_MIDI_NOTE;
  var MAX_VISIBLE_MIDI = LAST_MIDI_NOTE - VISIBLE_KEY_COUNT + 1;
  var CONTEXT_LENGTH = 4;
  var CHORD_WINDOW_MS = 100;
  var CHORD_THRESHOLD = 0.52;
  var MILLISECONDS_PER_DAY = 24 * 60 * 60 * 1000;
  var FLUSH_BATCH_SIZE = 64;
  var MAX_QUEUED_EXAMPLES = 2000;
  var NOTES = ["C", "C#", "D", "D#", "E", "F", "F#", "G", "G#", "A", "A#", "B"];
  var SHORTCUTS = {
    a: 60, w: 61, s: 62, e: 63, d: 64, f: 65, t: 66,
    g: 67, y: 68, h: 69, u: 70, j: 71, k: 72
  };
  var SHORTCUT_BY_MIDI = {};
  Object.keys(SHORTCUTS).forEach(function (key) {
    SHORTCUT_BY_MIDI[SHORTCUTS[key]] = key.toUpperCase();
  });

  var piano = document.getElementById("piano");
  var rangeLabel = document.getElementById("range-label");
  var previousRangeButton = document.getElementById("range-previous");
  var nextRangeButton = document.getElementById("range-next");
  var melody = document.getElementById("melody");
  var playButton = document.getElementById("play-toggle");
  var status = document.getElementById("status");
  var model = PianoModel.load().model;
  var sharedModel = PianoModel.create();
  var audioContext = null;
  var dryGain = null;
  var reverb = null;
  var wetGain = null;
  var sampleBuffers = {};
  var isPlaying = false;
  var playbackTimer = null;
  var highlightTimers = {};
  var playbackContext = [];
  var firstVisibleMidi = 48;
  var pendingInputNotes = [];
  var inputTimer = null;
  var trainingQueue = [];
  var trainingTimer = null;
  var sendingTraining = false;
  var lastSharedFetch = 0;
  var retryBatchId = null;
  var canSync = window.location.protocol !== "file:";

  function midiForNote(index) {
    return FIRST_MIDI_NOTE + index;
  }

  function noteName(midi) {
    return NOTES[midi % 12] + (Math.floor(midi / 12) - 1);
  }

  function renderPianoRange() {
    piano.textContent = "";
    var whiteKeysBefore = 0;
    var lastVisibleMidi = Math.min(LAST_MIDI_NOTE, firstVisibleMidi + VISIBLE_KEY_COUNT - 1);
    for (var countMidi = firstVisibleMidi; countMidi <= lastVisibleMidi; countMidi++) {
      if ([1, 3, 6, 8, 10].indexOf(countMidi % 12) === -1) whiteKeysBefore++;
    }

    var whiteKeyCount = whiteKeysBefore;
    piano.style.setProperty("--white-key-count", String(whiteKeyCount));
    whiteKeysBefore = 0;
    for (var midi = firstVisibleMidi; midi <= lastVisibleMidi; midi++) {
      var index = midi - FIRST_MIDI_NOTE;
      var key = document.createElement("button");
      var pitchClass = midi % 12;
      var isBlack = [1, 3, 6, 8, 10].indexOf(pitchClass) !== -1;
      var shortcut = SHORTCUT_BY_MIDI[midi];
      var note = noteName(midi);
      key.type = "button";
      key.className = "key " + (isBlack ? "black" : "white");
      key.dataset.note = String(index);
      key.dataset.midi = String(midi);
      key.setAttribute("aria-label", note + (shortcut ? ", keyboard " + shortcut : ""));
      key.title = note;

      if (isBlack) {
        key.style.left = ((whiteKeysBefore - 0.31) / whiteKeyCount * 100) + "%";
      } else {
        whiteKeysBefore++;
        key.style.flexBasis = (100 / whiteKeyCount) + "%";
        if (pitchClass === 0) {
          var octaveLabel = document.createElement("span");
          octaveLabel.className = "key-octave";
          octaveLabel.textContent = note;
          key.appendChild(octaveLabel);
        }
      }

      if (shortcut) {
        var shortcutLabel = document.createElement("span");
        shortcutLabel.className = "key-shortcut";
        shortcutLabel.textContent = shortcut;
        key.appendChild(shortcutLabel);
      }

      key.addEventListener("click", function (noteIndex) {
        return function () { notePlayed(noteIndex, true); };
      }(index));
      piano.appendChild(key);
    }
    rangeLabel.textContent = noteName(firstVisibleMidi) + " – " + noteName(lastVisibleMidi);
    previousRangeButton.disabled = firstVisibleMidi === MIN_VISIBLE_MIDI;
    nextRangeButton.disabled = firstVisibleMidi === MAX_VISIBLE_MIDI;
  }

  function render() {
    melody.textContent = "";
    model.history.slice(-16).forEach(function (note) {
      var chip = document.createElement("span");
      chip.className = "melody-note";
      chip.textContent = noteName(midiForNote(note));
      melody.appendChild(chip);
    });

    playButton.textContent = isPlaying ? "Stop" : "Play";
    playButton.setAttribute("aria-pressed", String(isPlaying));
    playButton.classList.toggle("playing", isPlaying);
  }

  function setStatus(message) {
    status.textContent = message;
  }

  function createReverb() {
    var sampleRate = audioContext.sampleRate;
    var impulse = audioContext.createBuffer(2, Math.floor(sampleRate * 0.75), sampleRate);
    for (var channel = 0; channel < impulse.numberOfChannels; channel++) {
      var data = impulse.getChannelData(channel);
      for (var sample = 0; sample < data.length; sample++) {
        var time = sample / sampleRate;
        data[sample] = (Math.random() * 2 - 1) * Math.pow(1 - time / 0.75, 3);
      }
    }

    reverb = audioContext.createConvolver();
    reverb.buffer = impulse;
    wetGain = audioContext.createGain();
    wetGain.gain.value = 0.12;
    reverb.connect(wetGain);
    wetGain.connect(audioContext.destination);

    dryGain = audioContext.createGain();
    dryGain.gain.value = 0.8;
    dryGain.connect(audioContext.destination);
  }

  function createPianoSample(midi) {
    var sampleRate = audioContext.sampleRate;
    var duration = midi < 40 ? 3 : (midi > 88 ? 1.4 : 2);
    var buffer = audioContext.createBuffer(1, Math.floor(sampleRate * duration), sampleRate);
    var data = buffer.getChannelData(0);
    var frequency = 440 * Math.pow(2, (midi - 69) / 12);
    var partials = [1, 0.55, 0.3, 0.19, 0.12, 0.08, 0.055, 0.035, 0.025, 0.016];
    var decay = 1.25 + (midi - FIRST_MIDI_NOTE) / (LAST_MIDI_NOTE - FIRST_MIDI_NOTE) * 1.4;

    for (var sample = 0; sample < data.length; sample++) {
      var time = sample / sampleRate;
      var value = 0;
      for (var partial = 1; partial <= partials.length; partial++) {
        var harmonic = frequency * partial *
          Math.sqrt(1 + 0.00012 * partial * partial);
        if (harmonic >= sampleRate * 0.48) break;

        var envelope = Math.exp(-time * (decay + (partial - 1) * 1.1));
        var amplitude = partials[partial - 1] * envelope;
        var phase = 2 * Math.PI * harmonic * time;
        value += amplitude * Math.sin(phase);
        if (partial <= 2) {
          value += amplitude * 0.2 * Math.sin(phase * 1.0008);
        }
      }

      var attack = Math.min(1, time * 280);
      var hammer = sample < sampleRate * 0.006
        ? (Math.random() * 2 - 1) * (1 - time * 160) * 0.025
        : 0;
      data[sample] = (value * attack + hammer) * 0.28;
    }
    return buffer;
  }

  function playSound(noteIndex) {
    var AudioContextType = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextType) {
      setStatus("Sound is not supported in this browser.");
      return;
    }

    try {
      if (!audioContext) {
        audioContext = new AudioContextType();
        createReverb();
      }
      if (audioContext.state === "suspended") audioContext.resume();

      var midi = midiForNote(noteIndex);
      var source = audioContext.createBufferSource();
      if (!sampleBuffers[midi]) sampleBuffers[midi] = createPianoSample(midi);
      source.buffer = sampleBuffers[midi];
      source.connect(dryGain);
      source.connect(reverb);
      source.start();
    } catch (error) {
      setStatus("Sound could not start. Press a key to try again.");
    }
  }

  function highlight(noteIndex) {
    var key = piano.querySelector('[data-note="' + noteIndex + '"]');
    if (!key) {
      var midi = midiForNote(noteIndex);
      firstVisibleMidi = Math.max(
        MIN_VISIBLE_MIDI,
        Math.min(MAX_VISIBLE_MIDI, midi - Math.floor(VISIBLE_KEY_COUNT / 2))
      );
      renderPianoRange();
      key = piano.querySelector('[data-note="' + noteIndex + '"]');
    }
    if (!key) return;
    key.classList.add("active");
    if (highlightTimers[noteIndex]) window.clearTimeout(highlightTimers[noteIndex]);
    highlightTimers[noteIndex] = window.setTimeout(function () {
      key.classList.remove("active");
      delete highlightTimers[noteIndex];
    }, 350);
  }

  function stopPlayback() {
    isPlaying = false;
    if (playbackTimer) {
      window.clearTimeout(playbackTimer);
      playbackTimer = null;
    }
    render();
  }

  function notePlayed(noteIndex, learnFromNote) {
    if (learnFromNote) {
      if (isPlaying) stopPlayback();
      if (pendingInputNotes.indexOf(noteIndex) === -1) {
        pendingInputNotes.push(noteIndex);
      }
      if (inputTimer) window.clearTimeout(inputTimer);
      inputTimer = window.setTimeout(flushPendingInput, CHORD_WINDOW_MS);
    }

    playSound(noteIndex);
    highlight(noteIndex);
  }

  function flushPendingInput() {
    if (inputTimer) {
      window.clearTimeout(inputTimer);
      inputTimer = null;
    }
    if (!pendingInputNotes.length) return;

    var notes = pendingInputNotes;
    var context = model.history.slice(-CONTEXT_LENGTH);
    pendingInputNotes = [];
    PianoModel.learnChord(model, context, notes);
    if (PianoModel.save(model)) setStatus("");
    else setStatus("Notes could not be saved in this browser.");
    queueTrainingExample(context, notes);
    render();
  }

  function playNextNote() {
    if (!isPlaying) return;
    var distribution = PianoModel.predict(model, playbackContext, sharedModel);
    var playedNotes = model.history.filter(function (note, index, history) {
      return history.indexOf(note) === index;
    });
    var candidateScores = Array(PianoModel.noteCount).fill(0);
    playedNotes.forEach(function (note) {
      candidateScores[note] = distribution[note];
    });
    var chord = [PianoModel.sample(candidateScores)];
    playedNotes.forEach(function (note) {
      if (chord[0] === note) return;
      var probability = Math.max(0, Math.min(1,
        (distribution[note] - CHORD_THRESHOLD) / (1 - CHORD_THRESHOLD)));
      if (Math.random() < probability) chord.push(note);
    });

    chord.forEach(function (note) {
      notePlayed(note, false);
      playbackContext.push(note);
      if (playbackContext.length > CONTEXT_LENGTH) playbackContext.shift();
    });
    playbackTimer = window.setTimeout(playNextNote, 420);
  }

  function togglePlayback() {
    if (pendingInputNotes.length) flushPendingInput();
    if (isPlaying) {
      stopPlayback();
      return;
    }
    if (!model.history.length) {
      setStatus("Play a few notes first.");
      return;
    }

    playbackContext = model.history.slice(-CONTEXT_LENGTH);
    isPlaying = true;
    render();
    playNextNote();
  }

  function createBatchId() {
    if (window.crypto && typeof window.crypto.randomUUID === "function") {
      return window.crypto.randomUUID();
    }
    return Date.now().toString(36) + "-" + Math.random().toString(36).slice(2);
  }

  function queueTrainingExample(context, notes) {
    if (!canSync) return;
    if (trainingQueue.length >= MAX_QUEUED_EXAMPLES) {
      trainingQueue.shift();
      setStatus("Shared learning is busy; this note is saved on your device.");
    }
    trainingQueue.push({ context: context, notes: notes });
    if (trainingQueue.length >= FLUSH_BATCH_SIZE) {
      flushTrainingQueue();
    } else if (!trainingTimer && !sendingTraining) {
      trainingTimer = window.setTimeout(flushTrainingQueue, 700);
    }
  }

  async function flushTrainingQueue() {
    if (trainingTimer) {
      window.clearTimeout(trainingTimer);
      trainingTimer = null;
    }
    if (!canSync || sendingTraining || !trainingQueue.length) return;

    var batch = trainingQueue.splice(0, FLUSH_BATCH_SIZE);
    var batchId = retryBatchId || createBatchId();
    sendingTraining = true;
    var retryDelay = 0;
    try {
      var response = await window.fetch("./api/learn.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ requestId: batchId, examples: batch }),
        keepalive: true
      });

      if (response.status === 429) {
        trainingQueue = batch.concat(trainingQueue).slice(-MAX_QUEUED_EXAMPLES);
        retryBatchId = batchId;
        setStatus("Shared learning is busy; your notes are saved here.");
        retryDelay = MILLISECONDS_PER_DAY - (Date.now() % MILLISECONDS_PER_DAY) + 1000;
      } else if (!response.ok) {
        throw new Error("Shared learning is temporarily unavailable.");
      } else {
        retryBatchId = null;
        if (status.textContent.indexOf("Shared") === 0) setStatus("");
      }
    } catch (error) {
      trainingQueue = batch.concat(trainingQueue).slice(-MAX_QUEUED_EXAMPLES);
      retryBatchId = batchId;
      setStatus("Shared learning is unavailable; your notes are saved here.");
      retryDelay = 60000;
    } finally {
      sendingTraining = false;
      if (trainingQueue.length && !trainingTimer) {
        trainingTimer = window.setTimeout(flushTrainingQueue, retryDelay || 250);
      }
    }
  }

  async function loadSharedModel() {
    if (!canSync) return;
    try {
      var response = await window.fetch("./api/model.php", { cache: "no-store" });
      if (!response.ok) throw new Error("Shared model is unavailable.");
      var nextModel = await response.json();
      if (!PianoModel.isValid(nextModel)) throw new Error("Shared model is invalid.");
      sharedModel = nextModel;
      lastSharedFetch = Date.now();
      if (status.textContent.indexOf("Shared") === 0) setStatus("");
    } catch (error) {
      if (status.textContent.indexOf("Sound") !== 0) {
        setStatus("Shared model unavailable; your notes stay on this device.");
      }
    }
  }

  playButton.addEventListener("click", togglePlayback);
  previousRangeButton.addEventListener("click", function () {
    firstVisibleMidi = Math.max(MIN_VISIBLE_MIDI, firstVisibleMidi - 12);
    renderPianoRange();
  });
  nextRangeButton.addEventListener("click", function () {
    firstVisibleMidi = Math.min(MAX_VISIBLE_MIDI, firstVisibleMidi + 12);
    renderPianoRange();
  });
  document.addEventListener("keydown", function (event) {
    if (event.altKey || event.ctrlKey || event.metaKey || event.repeat) return;
    var target = event.target;
    if (target && (target.isContentEditable || /INPUT|TEXTAREA|SELECT/.test(target.tagName))) return;
    var midi = SHORTCUTS[event.key.toLowerCase()];
    if (midi !== undefined) {
      event.preventDefault();
      notePlayed(midi - FIRST_MIDI_NOTE, true);
    }
  });

  document.addEventListener("visibilitychange", function () {
    if (document.hidden) {
      flushPendingInput();
      flushTrainingQueue();
    } else if (Date.now() - lastSharedFetch >= 3600000) {
      loadSharedModel();
    }
  });

  if (canSync) {
    loadSharedModel();
    window.setInterval(loadSharedModel, 3600000);
  }
  renderPianoRange();
  render();
}());
