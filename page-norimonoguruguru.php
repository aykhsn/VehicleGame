<?php
/*
Template Name: のりものグルグル
*/
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> <?php Arkhe::root_attrs(); ?>>
<head>
<meta charset="utf-8">
<meta name="format-detection" content="telephone=no">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, viewport-fit=cover">
<?php
	wp_head();
	$setting = Arkhe::get_setting(); // SETTING取得
?>
<style>
    body {
        margin: 0;
        padding: 0;
        overflow: hidden;
        background-color: #7fcc99;
    }
    canvas {
        display: block;
    }
</style>
</head>
<body>
    <script src="https://cdn.jsdelivr.net/npm/phaser@3/dist/phaser.min.js"></script>
    <script>
    const assetsUrl = "<?php echo get_template_directory_uri(); ?>/assets/games";

    class VehicleGame extends Phaser.Scene {
    constructor() {
        super({ key: 'VehicleGame' });
    }

    preload() {
        this.load.image('patrolcar', `${assetsUrl}/patrolcar.png`);
        this.load.image('firetruck', `${assetsUrl}/firetruck.png`);
        this.load.image('ambulance', `${assetsUrl}/ambulance.png`);
        this.load.image('redbutton', `${assetsUrl}/button.png`);
        this.load.audio('patrolcar_siren', `${assetsUrl}/patrolcar_siren.mp3`);
        this.load.audio('firetruck_siren', `${assetsUrl}/firetruck_siren.mp3`);
        this.load.audio('ambulance_siren', `${assetsUrl}/ambulance_siren.mp3`);
        this.load.audio('button_click', `${assetsUrl}/pecho.mp3`);
        this.load.audio('speed_up', `${assetsUrl}/charge.mp3`);
    }

    create() {
        this.roadGraphics = this.add.graphics();
        this.dashLineGraphics = this.add.graphics();
        this.vehicle = this.add.image(0, 0, 'patrolcar').setInteractive();
        this.redButton = this.add.image(this.scale.width / 2, this.scale.height / 2, 'redbutton').setInteractive();
        this.buttonClickSound = this.sound.add('button_click');
        this.speedUpSound = this.sound.add('speed_up');
        this.angle = -Math.PI / 2; // パトロールカーを初期位置に設定（上側）
        this.initialSpeed = 0.01;
        this.speed = this.initialSpeed;
        this.isMoving = false;
        this.currentVehicle = 'patrolcar';
        this.sirenSounds = {
            patrolcar: this.sound.add('patrolcar_siren', { loop: true }),
            firetruck: this.sound.add('firetruck_siren', { loop: true }),
            ambulance: this.sound.add('ambulance_siren', { loop: true })
        };
        this.currentSiren = this.sirenSounds.patrolcar;

        this.resize();

        this.vehicle.on('pointerdown', this.accelerate, this);
        this.redButton.on('pointerdown', this.onRedButtonDown, this);
    }

    update() {
        if (this.isMoving) {
            this.angle -= this.speed;
            const radius = this.roadRadius;
            const centerX = this.scale.width / 2;
            const centerY = this.scale.height / 2;

            this.vehicle.x = centerX + radius * Math.cos(this.angle);
            this.vehicle.y = centerY + radius * Math.sin(this.angle);

            this.vehicle.rotation = this.angle + Math.PI / 2;
        }
    }

    startMovement() {
        if (!this.isMoving) {
            this.isMoving = true;
            this.currentSiren.play();
            this.speed = this.initialSpeed;
            this.tweens.add({
                targets: this.redButton,
                alpha: 0,
                duration: 500,
                onComplete: () => {
                    this.redButton.disableInteractive(); // ボタンを無効化
                }
            });

            this.time.delayedCall(6000, () => { // 6秒間走行
                this.tweens.add({
                    targets: this.currentSiren,
                    volume: 0,
                    duration: 3000, // 3秒間でサイレンの音量を下げる
                    onComplete: () => {
                        this.currentSiren.stop();
                        this.currentSiren.setVolume(1);
                    }
                });

                this.tweens.add({
                    targets: this,
                    speed: 0,
                    duration: 3000, // 3秒間で速度を減速
                    onUpdate: (tween, target) => {
                        target.speed = tween.getValue();
                    }
                });
            });

            this.time.delayedCall(9000, () => { // 9秒後に停止
                this.isMoving = false;
                this.redButton.setInteractive(); // ボタンを再度有効化
                this.tweens.add({
                    targets: this.redButton,
                    alpha: 1,
                    duration: 500
                });
            });
        }
    }

    accelerate() {
        if (this.isMoving) {
            this.speedUpSound.play();
            this.speed = this.initialSpeed * 4;
            this.time.delayedCall(1000, () => {
                this.speed = this.initialSpeed;
            });
        } else {
            this.startMovement();
        }
    }

    onRedButtonDown() {
        this.buttonClickSound.play();
        this.tweens.add({
            targets: this.redButton,
            scale: this.redButton.scale * 0.9,
            duration: 100,
            yoyo: true,
            onComplete: () => {
                this.switchVehicle();
            }
        });
    }

    switchVehicle() {
        this.tweens.add({
            targets: this.vehicle,
            scale: 0,
            duration: 500,
            onComplete: () => {
                if (this.isMoving) {
                    this.currentSiren.stop();
                }

                if (this.currentVehicle === 'patrolcar') {
                    this.currentVehicle = 'firetruck';
                    this.vehicle.setTexture('firetruck');
                    this.currentSiren = this.sirenSounds.firetruck;
                } else if (this.currentVehicle === 'firetruck') {
                    this.currentVehicle = 'ambulance';
                    this.vehicle.setTexture('ambulance');
                    this.currentSiren = this.sirenSounds.ambulance;
                } else if (this.currentVehicle === 'ambulance') {
                    this.currentVehicle = 'patrolcar';
                    this.vehicle.setTexture('patrolcar');
                    this.currentSiren = this.sirenSounds.patrolcar;
                }

                this.vehicle.setScale(0);
                this.tweens.add({
                    targets: this.vehicle,
                    scale: this.minSize / 10 / 150,
                    duration: 500,
                    onComplete: () => {
                        this.startMovement(); // 車両が入れ替わった後に走り出す
                    }
                });
            }
        });
    }

    resize() {
        const minSize = Math.min(this.scale.width, this.scale.height);
        this.minSize = minSize;
        this.roadRadius = minSize / 2 - minSize * 0.2;

        this.roadGraphics.clear();
        this.roadGraphics.lineStyle(minSize * 0.1, 0xCCCCCC, 1);
        this.roadGraphics.strokeCircle(this.scale.width / 2, this.scale.height / 2, this.roadRadius);

        this.drawDashedLine(minSize);

        this.vehicle.setScale(minSize / 10 / 150);
        this.vehicle.setPosition(this.scale.width / 2, this.scale.height / 2 - this.roadRadius);

        this.redButton.setScale(minSize / 1600);
        this.redButton.setPosition(this.scale.width / 2, this.scale.height / 2);
    }

    drawDashedLine(minSize) {
        this.dashLineGraphics.clear();
        const dashLength = minSize * 0.04;
        const gapLength = minSize * 0.03;
        const radius = this.roadRadius;
        const centerX = this.scale.width / 2;
        const centerY = this.scale.height / 2;
        const dashColor = 0xEEEEEE;
        const lineWidth = minSize * 0.01;

        this.dashLineGraphics.lineStyle(lineWidth, dashColor, 1);

        for (let angle = 0; angle < 2 * Math.PI; angle += (dashLength + gapLength) / radius) {
            const startX = centerX + radius * Math.cos(angle);
            const startY = centerY + radius * Math.sin(angle);
            const endX = centerX + radius * Math.cos(angle + dashLength / radius);
            const endY = centerY + radius * Math.sin(angle + dashLength / radius);

            this.dashLineGraphics.beginPath();
            this.dashLineGraphics.moveTo(startX, startY);
            this.dashLineGraphics.lineTo(endX, endY);
            this.dashLineGraphics.strokePath();
        }
    }
}

const config = {
    type: Phaser.AUTO,
    scale: {
        mode: Phaser.Scale.RESIZE,
        autoCenter: Phaser.Scale.CENTER_BOTH
    },
    backgroundColor: '#7fcc99',
    scene: VehicleGame
};

const game = new Phaser.Game(config);

window.addEventListener('resize', () => {
    game.scene.scenes[0].resize();
});

    </script>
</body>
</html>